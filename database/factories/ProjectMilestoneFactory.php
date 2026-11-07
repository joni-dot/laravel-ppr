<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => rtrim(fake()->unique()->sentence(3, false), '.'),
            'position' => fake()->unique()->numberBetween(0, 30000),
            'is_need_date' => false,
        ];
    }

    public function needDate(): static
    {
        return $this->state(['is_need_date' => true]);
    }
}
