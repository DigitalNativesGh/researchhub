<?php

namespace App\Notifications\Researcher;

use App\Models\Project;
use App\Notifications\WorkflowNotification;
use Illuminate\Notifications\Messages\MailMessage;

class Stage1Delivered extends WorkflowNotification
{
    public function __construct(Project $project, public bool $updated = false)
    {
        parent::__construct($project);
    }

    protected function title(): string
    {
        return $this->updated ? 'Updated Chapters 1–3 and instrument' : 'Your Chapters 1–3 and instrument are ready';
    }

    protected function message(): string
    {
        return $this->updated
            ? 'A corrected version of your Chapters 1–3 and/or instrument is ready to download.'
            : 'Your Chapters 1–3 and data collection instrument are ready to download. Use the instrument to collect your data, then upload it to continue.';
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'task_alt';
    }

    protected function action(): string
    {
        return 'Download your documents';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        return $this->updated ? $mail : $mail->line('When your data is ready, open the project and choose “Upload data”.');
    }
}
