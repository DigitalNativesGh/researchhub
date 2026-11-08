<?php

namespace App\Services\Workflow;

use App\Enums\WorkflowStatus;
use App\Models\Project;

/** The researcher's progress tracker: setup and the three stages, in plain language. */
class ProjectTimeline
{
    /** @return list<array{key:string, title:string, state:string, detail:string, date:?string}> state: done|current|pending */
    public function stages(Project $project): array
    {
        $position = $this->position($project);
        $delivered = $project->deliveredDocuments()->get();

        $items = [
            ['setup', 'Submit your project', 'Your title, project details and sources.', $project->submitted_at],
            ['stage1', 'Stage 1 · Chapters 1–3 and instrument', 'Written by our team from your sources and delivered here.', $delivered->where('stage', 1)->min('delivered_at')],
            ['stage2', 'Stage 2 · Your data', 'Collect data with the instrument, then upload it here.', $project->data_submitted_at],
            ['stage3', 'Stage 3 · Complete thesis', 'Chapters 1–5 with references and appendices, written by our team from your data.', $delivered->where('stage', 3)->min('delivered_at')],
        ];

        $out = [];
        foreach ($items as $i => [$key, $title, $detail, $date]) {
            $state = match (true) {
                $project->workflow_status === WorkflowStatus::Completed, $i < $position => 'done',
                $i === $position => 'current',
                default => 'pending',
            };
            $out[] = ['key' => $key, 'title' => $title, 'state' => $state, 'detail' => $detail, 'date' => $date?->format('j M Y')];
        }

        return $out;
    }

    /** @return array{title:string, text:string, button:?string, href:?string, icon:string} */
    public function nextStep(Project $project): array
    {
        $status = $project->workflow_status;
        $message = $status->researcherMessage();

        return match ($status) {
            WorkflowStatus::Draft => ['title' => 'Finish setting up', 'text' => $message, 'button' => 'Add Sources', 'href' => route('projects.setup.sources', $project), 'icon' => 'upload_file'],
            WorkflowStatus::Stage1Delivered => ['title' => 'Collect and upload your data', 'text' => $message, 'button' => 'Upload Data', 'href' => route('projects.data', $project), 'icon' => 'upload'],
            WorkflowStatus::NeedsInfo => ['title' => 'We need some information', 'text' => $message, 'button' => null, 'href' => null, 'icon' => 'help'],
            WorkflowStatus::Completed => ['title' => 'Your thesis is ready', 'text' => $message, 'button' => null, 'href' => null, 'icon' => 'workspace_premium'],
            default => ['title' => $status->researcherLabel(), 'text' => $message, 'button' => null, 'href' => null, 'icon' => 'hourglass_top'],
        };
    }

    /** Index of the current step: 0 setup, 1 Stage 1, 2 data, 3 Stage 3. Paused projects show where they paused. */
    private function position(Project $project): int
    {
        $status = in_array($project->workflow_status, [WorkflowStatus::NeedsInfo, WorkflowStatus::OnHold], true)
            ? (WorkflowStatus::tryFrom((string) $project->resume_status) ?? WorkflowStatus::Submitted)
            : $project->workflow_status;

        return match ($status) {
            WorkflowStatus::Draft => 0,
            WorkflowStatus::Submitted, WorkflowStatus::Stage1InProgress => 1,
            WorkflowStatus::Stage1Delivered => 2,
            default => 3,
        };
    }
}
