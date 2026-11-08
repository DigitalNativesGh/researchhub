<?php

namespace App\Enums;

enum DatasetStatus: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case Valid = 'valid';
    case ValidWithWarnings = 'valid_with_warnings';
    case Invalid = 'invalid';
    case Accepted = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Validating => 'Validating',
            self::Valid => 'Valid',
            self::ValidWithWarnings => 'Valid with warnings',
            self::Invalid => 'Invalid',
            self::Accepted => 'Accepted',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Uploaded => 'pending',
            self::Validating => 'progress',
            self::Valid, self::Accepted => 'done',
            self::ValidWithWarnings => 'approval',
            self::Invalid => 'failed',
        };
    }
}
