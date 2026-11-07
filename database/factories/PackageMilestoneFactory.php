<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageMilestone>
 */
class PackageMilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'project_milestone_id' => ProjectMilestone::factory(),
        ];
    }
}
