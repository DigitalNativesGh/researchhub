<?php

namespace App\Notifications\Admin;

use App\Models\Project;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Str;

class InformationProvided extends WorkflowNotification
{
    public function __construct(Project $project, public ?string $reply = null)
    {
        parent::__construct($project);
    }

    protected function title(): string
    {
        return 'Researcher answered your request';
    }

    protected function message(): string
    {
        return "{$this->project->user?->name} updated “{$this->project->title}”".($this->reply ? ': '.Str::limit($this->reply, 300) : '.');
    }

    protected function url(): string
    {
        return route('admin.projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'mark_chat_read';
    }
}
