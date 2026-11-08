<?php

namespace App\Enums;

enum CitationStyle: string
{
    case Apa7 = 'apa7';
    case Harvard = 'harvard';
    case Mla = 'mla';
    case Chicago = 'chicago';

    public function label(): string
    {
        return match ($this) {
            self::Apa7 => 'APA 7th edition',
            self::Harvard => 'Harvard',
            self::Mla => 'MLA 9th edition',
            self::Chicago => 'Chicago (author-date)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Apa7 => 'APA 7',
            self::Harvard => 'Harvard',
            self::Mla => 'MLA 9',
            self::Chicago => 'Chicago',
        };
    }

    public function referencesHeading(): string
    {
        return match ($this) {
            self::Mla => 'Works Cited',
            default => 'References',
        };
    }
}
