<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email (when the recipient has email alerts on) plus an in-app notification. Researcher notifications never
 * contain internal details such as the assigned writer or internal notes.
 */
abstract class WorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(public Project $project) {}

    abstract protected function title(): string;

    abstract protected function message(): string;

    abstract protected function url(): string;

    protected function icon(): string
    {
        return 'notifications';
    }

    protected function action(): string
    {
        return 'Open the project';
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email_alerts && $notifiable->hasVerifiedEmail() ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->project->reference().': '.$this->title())
            ->greeting('Hello '.$notifiable->firstName().',')
            ->line($this->message())
            ->action($this->action(), $this->url())
            ->line('You can switch these emails off under Profile Settings.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
            'icon' => $this->icon(),
        ];
    }
}
