<?php

namespace Database\Seeders;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Enums\PurchaseType;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A large made-up project for trying out lists and filters with realistic amounts of data:
 * about 3,000 packages, up to three levels deep. Admins see it without being members.
 *
 * Run with: sail artisan db:seed --class=DemoProjectSeeder
 */
class DemoProjectSeeder extends Seeder
{
    private const int TOP_LEVEL_PACKAGES = 700;

    private const array CATEGORIES = [
        'ELE' => 'Electrical',
        'MEC' => 'Mechanical',
        'CIV' => 'Civil works',
        'AUT' => 'Automation',
        'PIP' => 'Piping',
        'HVA' => 'Heating and ventilation',
    ];

    public function __construct(private readonly CreatePackage $createPackage) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo projects must never be added to production.');
        }

        DB::transaction(fn () => $this->addDemoProject());
    }

    private function addDemoProject(): void
    {
        $manager = User::factory()->create(['name' => 'Demo Manager']);
        $buyers = User::factory()->count(3)->create();
        $owners = User::factory()->count(6)->create();

        $project = Project::factory()
            ->withMember($manager, ProjectRole::ProjectManager)
            ->create(['name' => 'Demo power plant', 'code' => 'DEMO'.random_int(100, 999)]);
        $this->addMembers($project, $buyers, ProjectRole::Buyer);
        $this->addMembers($project, $owners, ProjectRole::PackageOwner);

        $categories = collect(self::CATEGORIES)->map(
            fn (string $name, string $code) => ProjectCategory::factory()->for($project)->create(['code' => $code, 'name' => $name]),
        )->values();

        foreach (range(1, self::TOP_LEVEL_PACKAGES) as $ignored) {
            $this->addPackageTree($project, $categories, $buyers, $owners, depth: 0);
        }
    }

    /**
     * @param  Collection<int, ProjectCategory>  $categories
     * @param  Collection<int, User>  $buyers
     * @param  Collection<int, User>  $owners
     */
    private function addPackageTree(Project $project, Collection $categories, Collection $buyers, Collection $owners, int $depth, ?Package $parent = null): void
    {
        $title = rtrim(fake()->sentence(random_int(1, 4), false), '.');
        $package = $this->createPackage->handle($project, $title, $parent->category ?? $categories->random(), $parent);

        $package->forceFill([
            'status' => fake()->randomElement(PackageStatus::cases()),
            'purchase_type' => fake()->optional(0.8)->randomElement(PurchaseType::cases()),
            'owner_id' => fake()->optional(0.7)->randomElement($owners)?->id,
            'buyer_id' => fake()->optional(0.8)->randomElement($buyers)?->id,
        ])->save();

        $subPackages = match ($depth) {
            0 => fake()->randomElement([0, 0, 1, 2, 3, 5]),
            1 => fake()->randomElement([0, 0, 0, 1, 2]),
            default => 0,
        };

        for ($count = 0; $count < $subPackages; $count++) {
            $this->addPackageTree($project, $categories, $buyers, $owners, $depth + 1, $package);
        }
    }

    /**
     * @param  Collection<int, User>  $people
     */
    private function addMembers(Project $project, Collection $people, ProjectRole $role): void
    {
        foreach ($people as $person) {
            $membership = $project->members()->make(['role' => $role]);
            $membership->user_id = $person->id;
            $membership->save();
        }
    }
}
