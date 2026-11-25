<?php

namespace App\Enums;

enum DegreeLevel: string
{
    case Undergraduate = 'undergraduate';
    case Mba = 'mba';
    case Msc = 'msc';
    case Mphil = 'mphil';
    case Phd = 'phd';
    case SystematicReview = 'systematic_review';
    case ResearchPaper = 'research_paper';

    public function label(): string
    {
        return match ($this) {
            self::Undergraduate => 'Undergraduate Research',
            self::Mba => 'MBA Research',
            self::Msc => 'MSc Research',
            self::Mphil => 'MPhil Research',
            self::Phd => 'PhD Thesis',
            self::SystematicReview => 'Systematic Review',
            self::ResearchPaper => 'Research Paper',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Undergraduate => 'Undergraduate',
            self::Mba => 'MBA',
            self::Msc => 'MSc',
            self::Mphil => 'MPhil',
            self::Phd => 'PhD',
            self::SystematicReview => 'Systematic Review',
            self::ResearchPaper => 'Research Paper',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Undergraduate => 'Thesis or final year projects for Bachelors degree requirements.',
            self::Mba => 'Advanced business case studies and strategic analysis reports.',
            self::Msc => 'Taught or research Masters dissertation with an empirical study.',
            self::Mphil => 'Masters level dissertation with original data contribution.',
            self::Phd => 'Doctoral-level scholarly contribution and extensive research.',
            self::SystematicReview => 'Structured review and synthesis of the published evidence on a question.',
            self::ResearchPaper => 'Journal-style article or standalone research paper.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Undergraduate => 'school',
            self::Mba => 'business_center',
            self::Msc => 'science',
            self::Mphil => 'history_edu',
            self::Phd => 'model_training',
            self::SystematicReview => 'manage_search',
            self::ResearchPaper => 'article',
        };
    }

    /** Typical total length, used as the default word target. */
    public function typicalWords(): int
    {
        return match ($this) {
            self::Undergraduate => 12000,
            self::Mba => 15000,
            self::Msc => 18000,
            self::Mphil => 30000,
            self::Phd => 80000,
            self::SystematicReview => 8000,
            self::ResearchPaper => 7000,
        };
    }

    public function typicalRange(): string
    {
        return match ($this) {
            self::Undergraduate => '10–15k words',
            self::Mba => '12–20k words',
            self::Msc => '15–20k words',
            self::Mphil => '25–40k words',
            self::Phd => '70–100k words',
            self::SystematicReview => '6–10k words',
            self::ResearchPaper => '5–8k words',
        };
    }
}
