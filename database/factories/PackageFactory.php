<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Top-level packages with made-up numbers. Use App\Actions\Packages\CreatePackage when
 * a test depends on real numbering or on sub-packages.
 *
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $runningNumber = fake()->unique()->numberBetween(1, 999999);

        return [
            'project_id' => Project::factory(),
            'number' => 'PKG-'.str_pad((string) $runningNumber, 6, '0', STR_PAD_LEFT),
            'number_path' => [$runningNumber],
            'title' => rtrim(fake()->sentence(3, false), '.'),
        ];
    }

    /**
     * Counts the made-up number as given, so packages added later never get it again.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Package $package) {
            $runningNumber = $package->number_path[0];

            Project::query()
                ->whereKey($package->project_id)
                ->where(fn ($project) => $project->whereNull('last_package_number')->orWhere('last_package_number', '<', $runningNumber))
                ->update(['last_package_number' => $runningNumber]);
        });
    }
}
