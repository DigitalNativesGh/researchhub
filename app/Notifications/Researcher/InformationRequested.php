<?php

namespace App\Notifications\Researcher;

use App\Notifications\WorkflowNotification;
use Illuminate\Support\Str;

class InformationRequested extends WorkflowNotification
{
    protected function title(): string
    {
        return 'We need some information from you';
    }

    protected function message(): string
    {
        return 'Our team has a question about “'.$this->project->title.'”: '.Str::limit((string) $this->project->info_request, 400);
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'help';
    }

    protected function action(): string
    {
        return 'Reply in your project';
    }
}
