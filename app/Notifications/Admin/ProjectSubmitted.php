<?php

namespace App\Notifications\Admin;

use App\Notifications\WorkflowNotification;

class ProjectSubmitted extends WorkflowNotification
{
    protected function title(): string
    {
        return 'New project submitted';
    }

    protected function message(): string
    {
        $p = $this->project;

        return "{$p->user?->name} submitted “{$p->title}” ({$p->degree_level->shortLabel()}, {$p->discipline}) with ".$p->sources()->count().' source file(s)'.($p->deadline ? ', due '.$p->deadline->format('j M Y') : '').'.';
    }

    protected function url(): string
    {
        return route('admin.projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'inbox';
    }

    protected function action(): string
    {
        return 'Open in the work queue';
    }
}
