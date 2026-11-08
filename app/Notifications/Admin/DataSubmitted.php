<?php

namespace App\Notifications\Admin;

use App\Notifications\WorkflowNotification;

class DataSubmitted extends WorkflowNotification
{
    protected function title(): string
    {
        return 'Research data submitted';
    }

    protected function message(): string
    {
        return "{$this->project->user?->name} submitted ".$this->project->submittedDatasets()->count()." data file(s) for “{$this->project->title}”. The project is ready for Stage 3.";
    }

    protected function url(): string
    {
        return route('admin.projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'dataset';
    }

    protected function action(): string
    {
        return 'Open in the work queue';
    }
}
