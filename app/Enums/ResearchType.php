<?php

namespace App\Enums;

enum ResearchType: string
{
    case Quantitative = 'quantitative';
    case Qualitative = 'qualitative';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Quantitative => 'Quantitative',
            self::Qualitative => 'Qualitative',
            self::Mixed => 'Mixed Methods',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Quantitative => 'Statistical inference, large-n surveys, experimental tests',
            self::Qualitative => 'Interviews, ethnography, narrative discourse analysis',
            self::Mixed => 'Sequential explanatory, convergent parallel designs',
        };
    }
}
