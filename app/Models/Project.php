<?php

namespace App\Models;

use App\Enums\CitationStyle;
use App\Enums\DegreeLevel;
use App\Enums\ResearchType;
use App\Enums\WorkflowStatus;
use App\Services\Claude\ClaudeGateway;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * A thesis project. The researcher supplies the title, details, sources and later the data; the team writes the
 * documents outside the platform and the admin delivers them (see App\Services\Workflow\ProjectWorkflow).
 *
 * Columns and tables left over from the retired AI-writing workflow (status, phase, abstract, ai_disclosure,
 * chapters, sections, frameworks, ...) are kept as an archive and no longer read or written.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const TITLE_SOURCES = ['own' => 'Researcher’s own title', 'suggested' => 'AI-suggested title', 'suggested_edited' => 'AI-suggested title, edited by the researcher'];

    protected $fillable = [
        'user_id', 'title', 'title_source', 'description', 'discipline', 'institution', 'degree_level', 'citation_style',
        'research_type', 'word_count_target', 'guidelines', 'writer_notes', 'deadline', 'keywords', 'status', 'phase', 'completed_at',
    ];
    // workflow_status and friends are deliberately not fillable: they change only through ProjectWorkflow.

    protected function casts(): array
    {
        return [
            'degree_level' => DegreeLevel::class,
            'citation_style' => CitationStyle::class,
            'research_type' => ResearchType::class,
            'workflow_status' => WorkflowStatus::class,
            'workflow_changed_at' => 'datetime',
            'data_submitted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'keywords' => 'array',
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Source papers (kind "source") and institutional guidelines or templates (kind "guidelines"). */
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class)->orderBy('id');
    }

    public function sources(): HasMany
    {
        return $this->papers()->where('kind', Paper::KIND_SOURCE);
    }

    public function guidelineFiles(): HasMany
    {
        return $this->papers()->where('kind', Paper::KIND_GUIDELINES);
    }

    public function datasets(): HasMany
    {
        return $this->hasMany(Dataset::class)->latest('id');
    }

    /** Data the researcher has submitted for Stage 3. */
    public function submittedDatasets(): HasMany
    {
        return $this->hasMany(Dataset::class)->whereNotNull('submitted_at')->whereNull('superseded_at')->orderBy('id');
    }

    /** Files uploaded but not yet submitted; the researcher can still replace them. */
    public function pendingDatasets(): HasMany
    {
        return $this->hasMany(Dataset::class)->whereNull('submitted_at')->whereNull('superseded_at')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->orderBy('id');
    }

    /** Deliverables released to the researcher. The only documents a researcher can ever reach. */
    public function deliveredDocuments(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->where('kind', ProjectDocument::DELIVERABLE)->whereNotNull('delivered_at')->orderBy('id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ProjectTransition::class)->orderBy('id');
    }

    /** "Mark as in progress" records: writer, expected date and notes (internal). */
    public function editingAssignments(): HasMany
    {
        return $this->hasMany(EditingAssignment::class)->latest('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ProjectAuditLog::class)->latest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class)->latest('id');
    }

    public function aiCalls(): HasMany
    {
        return $this->hasMany(AiCall::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', Payment::SUCCESS);
    }

    public function reference(): string
    {
        return 'RH-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** "MPhil · Quantitative · APA 7" */
    public function metaLine(): string
    {
        return collect([$this->degree_level?->shortLabel(), $this->research_type?->label(), $this->citation_style?->shortLabel()])->filter()->implode(' · ');
    }

    public function titleSourceLabel(): string
    {
        return self::TITLE_SOURCES[$this->title_source] ?? 'Not recorded';
    }

    public function needsQuantitativeData(): bool
    {
        return $this->research_type !== ResearchType::Qualitative;
    }

    public function needsQualitativeData(): bool
    {
        return $this->research_type !== ResearchType::Quantitative;
    }

    /**
     * Delete everything stored for this project: uploads, data, deliverables, and any copies the retired workflow
     * left in the Anthropic Files API. Call before deleting the row.
     */
    public function purgeFiles(): void
    {
        $disk = Storage::disk(config('researchhub.disk'));

        foreach ($this->papers as $paper) {
            $disk->delete($paper->file_path);
            if ($paper->anthropic_file_id) {
                rescue(fn () => app(ClaudeGateway::class)->deleteFile($paper->anthropic_file_id), report: false);
            }
        }
        foreach ($this->datasets as $dataset) {
            $disk->delete(array_filter([$dataset->file_path, $dataset->converted_path]));
        }
        $disk->deleteDirectory("documents/{$this->id}");
        $disk->deleteDirectory("exports/{$this->id}");
    }

    public function record(string $type, string $title, ?string $message = null, string $icon = 'info'): ProjectEvent
    {
        return $this->events()->create(compact('type', 'title', 'message', 'icon'));
    }

    /** Overall progress (0-100) used on project cards. */
    public function progressPercent(): int
    {
        $status = in_array($this->workflow_status, [WorkflowStatus::NeedsInfo, WorkflowStatus::OnHold], true)
            ? (WorkflowStatus::tryFrom((string) $this->resume_status) ?? WorkflowStatus::Submitted)
            : $this->workflow_status;

        return match ($status) {
            WorkflowStatus::Draft => 5,
            WorkflowStatus::Submitted => 15,
            WorkflowStatus::Stage1InProgress => 30,
            WorkflowStatus::Stage1Delivered => 50,
            WorkflowStatus::DataSubmitted => 65,
            WorkflowStatus::Stage3InProgress => 80,
            WorkflowStatus::Completed => 100,
            default => 15,
        };
    }
}
