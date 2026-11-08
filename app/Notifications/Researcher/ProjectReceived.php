<?php

namespace App\Notifications\Researcher;

use App\Notifications\WorkflowNotification;

class ProjectReceived extends WorkflowNotification
{
    protected function title(): string
    {
        return 'We have received your project';
    }

    protected function message(): string
    {
        return "Thank you. “{$this->project->title}” has been submitted. Our team will start on your Chapters 1–3 and data collection instrument, and we will let you know when they are ready to download.";
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'inbox';
    }
}
