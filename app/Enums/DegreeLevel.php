<?php

namespace App\Enums;

enum DegreeLevel: string
{
    case Undergraduate = 'undergraduate';
    case Mba = 'mba';
    case Mphil = 'mphil';
    case Phd = 'phd';

    public function label(): string
    {
        return match ($this) {
            self::Undergraduate => 'Undergraduate Research',
            self::Mba => 'MBA Research',
            self::Mphil => 'MPhil Research',
            self::Phd => 'PhD Thesis',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Undergraduate => 'Undergraduate',
            self::Mba => 'MBA',
            self::Mphil => 'MPhil',
            self::Phd => 'PhD',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Undergraduate => 'Thesis or final year projects for Bachelors degree requirements.',
            self::Mba => 'Advanced business case studies and strategic analysis reports.',
            self::Mphil => 'Masters level dissertation with original data contribution.',
            self::Phd => 'Doctoral-level scholarly contribution and extensive research.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Undergraduate => 'school',
            self::Mba => 'business_center',
            self::Mphil => 'history_edu',
            self::Phd => 'model_training',
        };
    }

    /** Typical total length, used as the default word target. */
    public function typicalWords(): int
    {
        return match ($this) {
            self::Undergraduate => 12000,
            self::Mba => 15000,
            self::Mphil => 30000,
            self::Phd => 80000,
        };
    }

    public function typicalRange(): string
    {
        return match ($this) {
            self::Undergraduate => '10–15k words',
            self::Mba => '12–20k words',
            self::Mphil => '25–40k words',
            self::Phd => '70–100k words',
        };
    }
}
