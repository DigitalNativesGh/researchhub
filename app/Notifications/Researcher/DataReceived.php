<?php

namespace App\Notifications\Researcher;

use App\Notifications\WorkflowNotification;

class DataReceived extends WorkflowNotification
{
    protected function title(): string
    {
        return 'We have received your data';
    }

    protected function message(): string
    {
        return 'Thank you. Your data has been received. Our team will now prepare your complete thesis, and we will let you know when it is ready to download.';
    }

    protected function url(): string
    {
        return route('projects.show', $this->project);
    }

    protected function icon(): string
    {
        return 'inventory';
    }
}
