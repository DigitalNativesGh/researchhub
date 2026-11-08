<?php

namespace App\Services\Workflow;

use App\Enums\DocumentType;
use App\Enums\WorkflowStatus;
use App\Models\Dataset;
use App\Models\EditingAssignment;
use App\Models\Project;
use App\Models\ProjectAuditLog;
use App\Models\ProjectTransition;
use App\Models\User;
use App\Notifications\Admin\DataSubmitted;
use App\Notifications\Admin\InformationProvided;
use App\Notifications\Admin\ProjectSubmitted;
use App\Notifications\Researcher\DataReceived;
use App\Notifications\Researcher\InformationRequested;
use App\Notifications\Researcher\ProjectReceived;
use App\Notifications\Researcher\Stage1Delivered;
use App\Notifications\Researcher\ThesisDelivered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The only way a project's status changes. Every move is checked against WorkflowStatus::transitions(), recorded
 * in project_transitions with the acting user, and triggers the notifications.
 *
 *   draft → submitted → stage1_in_progress → stage1_delivered → data_submitted → stage3_in_progress → completed
 *   needs_info (researcher must answer) and on_hold (admin pause) return to the status they interrupted.
 */
class ProjectWorkflow
{
    public function __construct(private readonly DocumentStore $documents) {}

    /** Move a project, or throw InvalidTransition. Locks the row so concurrent requests cannot both move it. */
    public function transition(Project $project, WorkflowStatus $to, ?User $by = null, ?string $note = null, array $extra = []): Project
    {
        return DB::transaction(function () use ($project, $to, $by, $note, $extra) {
            $fresh = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $from = $fresh->workflow_status;

            if (! $from->canMoveTo($to)) {
                throw new InvalidTransition($from, $to);
            }

            $fresh->forceFill($extra + ['workflow_status' => $to, 'workflow_changed_at' => now()])->save();
            ProjectTransition::create(['project_id' => $fresh->id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $by?->id, 'note' => $note]);

            $project->setRawAttributes($fresh->getAttributes(), true);

            return $project;
        });
    }

    /* ---------------------------------------------------------------- Submission */

    /** The researcher submits title, details and sources. Sources are locked from here on. */
    public function submit(Project $project, User $by): void
    {
        $this->transition($project, WorkflowStatus::Submitted, $by, 'Project submitted', ['submitted_at' => now()]);
        ProjectAuditLog::record($project, 'project.submitted', 'Researcher submitted the project ('.$project->papers()->count().' files)', user: $by);

        $project->user?->notify(new ProjectReceived($project));
        $this->notifyAdmins(new ProjectSubmitted($project));
    }

    /* ---------------------------------------------------------------- The team's work */

    /** "Mark as in progress", with optional internal writer details (never shown to the researcher). */
    public function markInProgress(Project $project, User $admin, ?string $writer, ?string $expected, ?string $notes): EditingAssignment
    {
        [$to, $stage] = match ($project->workflow_status) {
            WorkflowStatus::Submitted => [WorkflowStatus::Stage1InProgress, 1],
            WorkflowStatus::DataSubmitted => [WorkflowStatus::Stage3InProgress, 3],
            default => throw new InvalidTransition($project->workflow_status, WorkflowStatus::Stage1InProgress),
        };

        $assignment = DB::transaction(function () use ($project, $to, $stage, $admin, $writer, $expected, $notes) {
            $this->transition($project, $to, $admin, $writer ? "Assigned to {$writer}" : 'Work started');

            return $project->editingAssignments()->create([
                'stage' => $stage, 'editor_name' => $writer, 'expected_return_at' => $expected, 'notes' => $notes, 'assigned_by' => $admin->id,
            ]);
        });

        ProjectAuditLog::record($project, 'work.started', "Stage {$stage} marked as in progress".($writer ? " ({$writer})" : ''), user: $admin, details: array_filter(['expected' => $expected]));

        return $assignment;
    }

    /**
     * Release the newest uploaded deliverables of a stage to the researcher. Delivering again after a correction
     * keeps the earlier versions in the history and makes the new ones what the researcher sees.
     *
     * @throws DeliveryNotReady when a required document has not been uploaded
     */
    public function deliver(Project $project, int $stage, User $admin): void
    {
        $required = $stage === 1 ? [DocumentType::Chapters, DocumentType::Instrument] : [DocumentType::Thesis];
        $latest = $this->documents->latestDeliverables($project, $stage);

        foreach ($required as $type) {
            if ($latest->where('type', $type)->isEmpty()) {
                throw new DeliveryNotReady("Upload the {$type->label()} before delivering.");
            }
        }

        $undelivered = $latest->whereNull('delivered_at');
        $already = $stage === 1
            ? $project->deliveredDocuments()->where('stage', 1)->exists()
            : $project->workflow_status === WorkflowStatus::Completed;

        if ($undelivered->isEmpty()) {
            throw new DeliveryNotReady('There is no new file to deliver.');
        }

        DB::transaction(function () use ($project, $stage, $admin, $undelivered, $already) {
            if (! $already) {
                $this->transition($project, $stage === 1 ? WorkflowStatus::Stage1Delivered : WorkflowStatus::Completed, $admin, 'Documents delivered', $stage === 3 ? ['completed_at' => now()] : []);
            }
            foreach ($undelivered as $document) {
                $document->forceFill(['delivered_at' => now()])->save();
            }
            $project->editingAssignments()->where('stage', $stage)->whereNull('completed_at')->update(['completed_at' => now()]);
        });

        foreach ($undelivered as $document) {
            ProjectAuditLog::record($project, 'document.delivered', "Delivered {$document->label} v{$document->version} to the researcher", $document, user: $admin);
        }

        $project->user?->notify($stage === 1 ? new Stage1Delivered($project, $already) : new ThesisDelivered($project, $already));
    }

    /* ---------------------------------------------------------------- Stage 2: data */

    public function submitData(Project $project, User $by): void
    {
        $datasets = $project->pendingDatasets()->get();

        DB::transaction(function () use ($project, $by, $datasets) {
            $this->transition($project, WorkflowStatus::DataSubmitted, $by, $datasets->count().' data file(s) submitted', ['data_submitted_at' => now()]);
            Dataset::whereIn('id', $datasets->pluck('id'))->update(['submitted_at' => now()]);
        });

        ProjectAuditLog::record($project, 'data.submitted', 'Researcher submitted '.$datasets->count().' data file(s)', user: $by, details: ['files' => $datasets->pluck('original_name')->all()]);
        $project->user?->notify(new DataReceived($project));
        $this->notifyAdmins(new DataSubmitted($project));
    }

    /* ---------------------------------------------------------------- Needs information / on hold */

    /** Ask the researcher for something. Their sources (Stage 1) or data (Stage 3) unlock until they answer. */
    public function requestInfo(Project $project, User $admin, string $message): void
    {
        $from = $project->workflow_status;
        $this->transition($project, WorkflowStatus::NeedsInfo, $admin, $message, ['resume_status' => $from->value, 'info_request' => $message]);
        ProjectAuditLog::record($project, 'info.requested', 'Asked the researcher for information', user: $admin, details: ['message' => $message]);
        $project->user?->notify(new InformationRequested($project));
    }

    /** The researcher answers; the project returns to where it was. New data files join the submission. */
    public function provideInfo(Project $project, User $by, ?string $reply): void
    {
        $to = $this->resumeTarget($project);

        DB::transaction(function () use ($project, $by, $reply, $to) {
            $this->transition($project, $to, $by, $reply ? 'Researcher replied: '.$reply : 'Researcher updated the project', ['resume_status' => null, 'info_request' => null]);
            if ($to->stage() === 3) {
                $project->pendingDatasets()->update(['submitted_at' => now()]);
            }
        });

        ProjectAuditLog::record($project, 'info.provided', 'Researcher answered the request for information', user: $by, details: array_filter(['reply' => $reply]));
        $this->notifyAdmins(new InformationProvided($project, $reply));
    }

    public function hold(Project $project, User $admin, ?string $reason): void
    {
        $from = $project->workflow_status;
        $this->transition($project, WorkflowStatus::OnHold, $admin, $reason ?: 'Put on hold', ['resume_status' => $from->value]);
        ProjectAuditLog::record($project, 'project.held', 'Project put on hold', user: $admin, details: array_filter(['reason' => $reason]));
    }

    /** Resume a held project, or withdraw a request for information. */
    public function resume(Project $project, User $admin): void
    {
        $this->transition($project, $this->resumeTarget($project), $admin, 'Resumed', ['resume_status' => null, 'info_request' => null]);
        ProjectAuditLog::record($project, 'project.resumed', 'Project resumed', user: $admin);
    }

    /** Where a paused project returns to. A project that is not paused has nowhere to resume to. */
    private function resumeTarget(Project $project): WorkflowStatus
    {
        $from = $project->workflow_status;
        $to = WorkflowStatus::tryFrom((string) $project->resume_status);

        if (! in_array($from, [WorkflowStatus::NeedsInfo, WorkflowStatus::OnHold], true) || $to === null) {
            throw new InvalidTransition($from, $to ?? $from);
        }

        return $to;
    }

    /** True while the researcher may change their sources: before submitting, or when asked for information in Stage 1. */
    public static function sourcesOpen(Project $project): bool
    {
        return $project->workflow_status === WorkflowStatus::Draft
            || ($project->workflow_status === WorkflowStatus::NeedsInfo && WorkflowStatus::tryFrom((string) $project->resume_status)?->stage() === 1);
    }

    /** True while the researcher may change their data: Stage 2, or when asked for information in Stage 3. */
    public static function dataOpen(Project $project): bool
    {
        return $project->workflow_status === WorkflowStatus::Stage1Delivered
            || ($project->workflow_status === WorkflowStatus::NeedsInfo && WorkflowStatus::tryFrom((string) $project->resume_status)?->stage() === 3);
    }

    private function notifyAdmins(object $notification): void
    {
        Notification::send(User::where('is_admin', true)->whereNull('suspended_at')->get(), $notification);
    }
}
