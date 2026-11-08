<?php

namespace App\Services\Workflow;

use App\Enums\WorkflowStatus;
use RuntimeException;

class InvalidTransition extends RuntimeException
{
    public function __construct(public readonly WorkflowStatus $from, public readonly WorkflowStatus $to)
    {
        parent::__construct("A project cannot move from \"{$from->label()}\" to \"{$to->label()}\".");
    }
}
