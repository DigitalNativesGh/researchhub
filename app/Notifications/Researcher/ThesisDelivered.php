<?php

namespace App\Notifications\Researcher;

use App\Models\Project;
use App\Notifications\WorkflowNotification;

class ThesisDelivered extends WorkflowNotification
{
    public function __construct(Project $project, public bool $updated = false)
    {
        parent::__construct($project);
    }

    protected function title(): string
    {
        return $this->updated ? 'Updated final thesis' : 'Your final thesis is ready';
    }

    protected function message(): string
    {
        return $this->updated
            ? 'A corrected version of your final thesis is ready to download.'
            : 'Your complete, edited thesis is ready to download.';
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'workspace_premium';
    }

    protected function action(): string
    {
        return 'Download your thesis';
    }
}
