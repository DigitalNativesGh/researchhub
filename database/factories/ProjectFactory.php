<?php

namespace Database\Factories;

use App\Enums\CitationStyle;
use App\Enums\DegreeLevel;
use App\Enums\ResearchType;
use App\Enums\WorkflowStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(10), '.'),
            'title_source' => 'own',
            'institution' => 'University of Ghana',
            'description' => fake()->paragraph(),
            'discipline' => 'Education',
            'degree_level' => DegreeLevel::Mphil,
            'citation_style' => CitationStyle::Apa7,
            'research_type' => ResearchType::Quantitative,
            'word_count_target' => 25000,
            'status' => 'draft',
            'phase' => 1,
        ];
    }

    public function qualitative(): static
    {
        return $this->state(fn () => ['research_type' => ResearchType::Qualitative]);
    }

    public function workflow(WorkflowStatus $status): static
    {
        return $this->state(fn () => ['workflow_status' => $status, 'workflow_changed_at' => now()]);
    }
}
