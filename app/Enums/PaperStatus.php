<?php

namespace App\Enums;

enum PaperStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case NeedsReview = 'needs_review';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Parsing => 'Parsing',
            self::NeedsReview => 'Check metadata',
            self::Ready => 'Summarised',
            self::Failed => 'Failed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Parsing => 'progress',
            self::NeedsReview => 'approval',
            self::Ready => 'done',
            self::Failed => 'failed',
        };
    }
}
