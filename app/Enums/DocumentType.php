<?php

namespace App\Enums;

/** The deliverables of the editing workflow. */
enum DocumentType: string
{
    case Chapters = 'chapters_1_3';
    case Instrument = 'instrument';
    case Thesis = 'complete_thesis';

    public function label(): string
    {
        return match ($this) {
            self::Chapters => 'Chapters 1–3',
            self::Instrument => 'Data collection instrument',
            self::Thesis => 'Complete thesis',
        };
    }

    public function stage(): int
    {
        return $this === self::Thesis ? 3 : 1;
    }
}
