<?php

namespace Database\Seeders;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Enums\PurchaseType;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Scheduling\Days;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A large made-up project for trying out lists and filters with realistic amounts of data:
 * about 3,000 packages, up to three levels deep, most with milestone dates. Admins see it without being members.
 *
 * Run with: sail artisan db:seed --class=DemoProjectSeeder
 */
class DemoProjectSeeder extends Seeder
{
    private const int TOP_LEVEL_PACKAGES = 700;

    /**
     * Typical calendar days from the previous milestone, for each default milestone after the first:
     * request for quotation, offers, order, delivery, required on site, installed.
     */
    private const array LEAD_DAYS = [1 => [7, 21], 2 => [14, 30], 3 => [7, 21], 4 => [30, 200], 5 => [3, 14], 6 => [7, 30]];

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
            ->withDefaultMilestones()
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

        $this->addMilestoneDates($project);
    }

    /**
     * Gives most packages a plan, with progress so far and some forecasts, so some packages run late and some have time
     * to spare. Inserted in bulk because there are tens of thousands of rows.
     */
    private function addMilestoneDates(Project $project): void
    {
        $milestones = $project->milestones()->get(['id', 'is_need_date']);
        $today = now()->toDateString();
        $now = now();
        $startDates = [];
        $rows = [];

        $packages = DB::table('packages')->where('project_id', $project->id)->orderBy('number_path')->get(['id', 'parent_id']);

        foreach ($packages as $package) {
            // Sub-packages start around the same time as the package they belong to.
            $start = $package->parent_id === null
                ? Days::add($today, random_int(-300, 420))
                : Days::add($startDates[$package->parent_id], random_int(-30, 30));
            $startDates[$package->id] = $start;

            if (random_int(1, 100) <= 10) {
                continue;
            }

            foreach ($this->milestoneDates($milestones, $start, $today) as $milestoneId => $dates) {
                $rows[] = ['package_id' => $package->id, 'project_milestone_id' => $milestoneId, ...$dates, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('package_milestones')->insert($chunk);
        }
    }

    /**
     * One package's dates, going through the milestones in order. Each planned date is the previous one plus the typical
     * lead time; the need date gets some slack on top, and the package's own lead time sometimes grows after planning.
     *
     * @param  Collection<int, ProjectMilestone>  $milestones  in order, the default ones from AddDefaultMilestones
     * @return array<int, array{planned_date: string, forecast_date: string|null, actual_date: string|null, lead_days: int|null}>
     */
    private function milestoneDates(Collection $milestones, string $start, string $today): array
    {
        $planned = $start;
        $previousDone = null;
        $stillOnTrack = true;
        $dates = [];

        foreach ($milestones->values() as $index => $milestone) {
            [$shortest, $longest] = self::LEAD_DAYS[$index] ?? [7, 30];
            $plannedLead = $index === 0 ? null : random_int($shortest, $longest);
            $planned = $plannedLead === null ? $planned : Days::add($planned, $plannedLead);

            if ($milestone->is_need_date) {
                $planned = Days::add($planned, random_int(5, 60));
            }

            $leadDays = $plannedLead !== null && random_int(1, 100) <= 8 ? $plannedLead + random_int(5, 30) : $plannedLead;
            $actual = null;
            $forecast = null;

            if ($stillOnTrack && $planned < $today) {
                $actual = Days::add($planned, random_int(-5, 15));
                $actual = $previousDone !== null && $actual < $previousDone ? $previousDone : $actual;
                $actual = $actual > $today ? null : $actual;
            }

            if ($actual === null && $stillOnTrack && random_int(1, 100) <= 30) {
                $forecast = max($today, Days::add($planned, random_int(-10, 30)));
            }

            $stillOnTrack = $stillOnTrack && $actual !== null;
            $previousDone = $actual ?? $previousDone;
            $dates[$milestone->id] = ['planned_date' => $planned, 'forecast_date' => $forecast, 'actual_date' => $actual, 'lead_days' => $leadDays];
        }

        return $dates;
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
