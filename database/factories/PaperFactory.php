<?php

namespace Database\Factories;

use App\Enums\PaperStatus;
use App\Models\Paper;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A paper with metadata already extracted. The file path points at the private disk; tests that need the
 * bytes should Storage::fake() and put a SamplePdf there.
 */
class PaperFactory extends Factory
{
    protected $model = Paper::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'kind' => Paper::KIND_SOURCE,
            'original_name' => fake()->unique()->lastName().'_'.fake()->numberBetween(2015, 2025).'.pdf',
            'file_path' => 'papers/test/'.Str::uuid().'.pdf',
            'file_size' => 120_000,
            'sha256' => hash('sha256', Str::uuid()),
            'parse_status' => PaperStatus::Ready,
        ];
    }

    public function guidelines(): static
    {
        return $this->state(fn () => ['kind' => Paper::KIND_GUIDELINES]);
    }
}
