<?php

namespace Database\Factories;

use App\Actions\Projects\AddDefaultMilestones;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' '.fake()->randomElement(['plant', 'extension', 'upgrade', 'line']),
            'code' => 'P'.fake()->unique()->numberBetween(100, 99999),
            'description' => null,
            'risk_amber_days' => 7,
            'risk_red_days' => 0,
        ];
    }

    /**
     * The milestones a project made in the app starts with.
     */
    public function withDefaultMilestones(): static
    {
        return $this->afterCreating(fn (Project $project) => app(AddDefaultMilestones::class)->handle($project));
    }

    public function withMember(User $user, ProjectRole $role = ProjectRole::Viewer): static
    {
        return $this->afterCreating(function (Project $project) use ($user, $role) {
            ProjectMember::factory()->for($project)->for($user)->create(['role' => $role]);
        });
    }
}
