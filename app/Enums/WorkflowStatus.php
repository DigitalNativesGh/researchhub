<?php

namespace App\Enums;

/**
 * Where a project is in the three-stage service. The researcher submits a title, details and sources; the team
 * writes Chapters 1–3 and the instrument(s) (Stage 1); the researcher collects and uploads data (Stage 2); the team
 * writes the complete thesis (Stage 3). The admin delivers each document. No AI writes any part of a thesis.
 *
 * Valid moves are listed in transitions(); App\Services\Workflow\ProjectWorkflow enforces them. "Needs information"
 * and "on hold" return to the status recorded in projects.resume_status.
 */
enum WorkflowStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Stage1InProgress = 'stage1_in_progress';
    case Stage1Delivered = 'stage1_delivered';
    case DataSubmitted = 'data_submitted';
    case Stage3InProgress = 'stage3_in_progress';
    case Completed = 'completed';
    case NeedsInfo = 'needs_info';
    case OnHold = 'on_hold';

    /** States the team works from, which "needs information" and "on hold" can pause and return to. */
    public const RESUMABLE = [self::Submitted, self::Stage1InProgress, self::Stage1Delivered, self::DataSubmitted, self::Stage3InProgress];

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Stage1InProgress, self::Stage1Delivered, self::NeedsInfo, self::OnHold],
            self::Stage1InProgress => [self::Submitted, self::Stage1Delivered, self::NeedsInfo, self::OnHold],
            self::Stage1Delivered => [self::DataSubmitted, self::OnHold],
            self::DataSubmitted => [self::Stage3InProgress, self::Completed, self::NeedsInfo, self::OnHold],
            self::Stage3InProgress => [self::DataSubmitted, self::Completed, self::NeedsInfo, self::OnHold],
            self::Completed => [],
            self::NeedsInfo, self::OnHold => self::RESUMABLE,
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Admin-facing name. */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted · new',
            self::Stage1InProgress => 'Stage 1 in progress',
            self::Stage1Delivered => 'Stage 1 delivered · awaiting data',
            self::DataSubmitted => 'Data submitted',
            self::Stage3InProgress => 'Stage 3 in progress',
            self::Completed => 'Completed',
            self::NeedsInfo => 'Needs information from researcher',
            self::OnHold => 'On hold',
        };
    }

    /** Researcher-facing name. */
    public function researcherLabel(): string
    {
        return match ($this) {
            self::Draft => 'Setting up',
            self::Submitted => 'Received',
            self::Stage1InProgress => 'Chapters 1–3 in progress',
            self::Stage1Delivered => 'Ready for your data',
            self::DataSubmitted => 'Data received',
            self::Stage3InProgress => 'Final thesis in progress',
            self::Completed => 'Completed',
            self::NeedsInfo => 'Action needed',
            self::OnHold => 'Paused',
        };
    }

    /** Plain-language message for the researcher's progress tracker. */
    public function researcherMessage(): string
    {
        return match ($this) {
            self::Draft => 'Add your title, project details and sources, then submit your project.',
            self::Submitted => 'We have received your project. Our team will start on your Chapters 1–3 and data collection instrument shortly.',
            self::Stage1InProgress => 'Our team is working on your Chapters 1–3 and data collection instrument.',
            self::Stage1Delivered => 'Your Chapters 1–3 and instrument are ready. Download the instrument, collect your data, then upload it here.',
            self::DataSubmitted => 'We have received your data. Our team will start on your complete thesis shortly.',
            self::Stage3InProgress => 'Our team is working on your complete thesis.',
            self::Completed => 'Your final thesis is ready to download.',
            self::NeedsInfo => 'Our team needs some information from you before continuing. See the request below.',
            self::OnHold => 'Your project is paused. Our team will be in touch.',
        };
    }

    /** 1 = Chapters 1–3, 2 = data, 3 = final thesis; null before submission and while paused. */
    public function stage(): ?int
    {
        return match ($this) {
            self::Draft, self::NeedsInfo, self::OnHold => null,
            self::Submitted, self::Stage1InProgress => 1,
            self::Stage1Delivered => 2,
            self::DataSubmitted, self::Stage3InProgress, self::Completed => 3,
        };
    }

    /** Waiting on the team (the work queue). */
    public function needsAdmin(): bool
    {
        return in_array($this, [self::Submitted, self::Stage1InProgress, self::DataSubmitted, self::Stage3InProgress], true);
    }

    /** Waiting on the researcher. */
    public function needsResearcher(): bool
    {
        return in_array($this, [self::Draft, self::Stage1Delivered, self::NeedsInfo], true);
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Draft, self::Completed], true);
    }

    /** Badge style key used by <x-badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft, self::OnHold => 'pending',
            self::Submitted, self::DataSubmitted, self::Stage1Delivered, self::NeedsInfo => 'approval',
            self::Completed => 'done',
            default => 'progress',
        };
    }

    /** Group used by the researcher's My Projects filters. */
    public function group(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::Completed => 'completed',
            self::Stage1Delivered, self::NeedsInfo => 'review',
            default => 'progress',
        };
    }

    /** @return list<self> */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->group() === $group));
    }
}
