<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Packages\CreatePackage;
use App\Dashboard\ProjectAttention;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\BrowserTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectAttentionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $buyer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday; its week starts on Monday 8 March.
        $this->travelTo('2027-03-10 12:00:00');
        $this->owner = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->owner, ProjectRole::PackageOwner)
            ->withMember($this->buyer, ProjectRole::Buyer)
            ->create(['number_includes_category' => false, 'risk_amber_days' => 7, 'risk_red_days' => 0]);
    }

    public function test_the_tiles_count_packages_by_level_and_reason()
    {
        $plenty = ['Required on site' => ['planned_date' => '2027-12-01', 'forecast_date' => '2027-06-01']];
        $this->addPackage('Fine', dates: $plenty);
        $this->addPackage('Overdue', dates: [...$plenty, 'Order placed' => ['planned_date' => '2027-03-01']]);
        $this->addPackage('No buyer', buyer: null, dates: $plenty);
        $this->addPackage('No need date');
        $flagged = $this->addPackage('Flagged', dates: $plenty);
        $flagged->forceFill(['risk_flag_reason' => 'Supplier in trouble', 'risk_flagged_at' => now()])->save();
        $this->addPackage('Closed and overdue', status: PackageStatus::Closed, dates: ['Order placed' => ['planned_date' => '2027-03-01']]);

        $tiles = ProjectAttention::for($this->project)->tiles();

        $this->assertSame([
            'total' => 6, 'open' => 5, 'red' => 2, 'amber' => 2, 'overdue' => 1, 'dueSoon' => 0,
            'missingPeople' => 1, 'noNeedDate' => 1, 'flagged' => 1,
        ], $tiles);
    }

    public function test_due_soon_means_a_milestone_not_done_is_expected_within_fourteen_days()
    {
        $this->addPackage('Today', dates: ['Order placed' => ['forecast_date' => '2027-03-10']]);
        $this->addPackage('Last day', dates: ['Order placed' => ['forecast_date' => '2027-03-24']]);
        $this->addPackage('Too late', dates: ['Order placed' => ['forecast_date' => '2027-03-25']]);
        // Any milestone counts, even when an earlier one has no date yet or is already overdue.
        $this->addPackage('Earlier one overdue', dates: [
            'Specification ready' => ['planned_date' => '2027-03-01'],
            'Order placed' => ['forecast_date' => '2027-03-12'],
        ]);
        $this->addPackage('Done', dates: ['Order placed' => ['forecast_date' => '2027-03-12', 'actual_date' => '2027-03-09']]);
        $this->addPackage('Overdue instead', dates: ['Order placed' => ['planned_date' => '2027-03-09']]);
        $this->addPackage('Cancelled', status: PackageStatus::Cancelled, dates: ['Order placed' => ['forecast_date' => '2027-03-11']]);

        $this->assertSame(3, ProjectAttention::for($this->project)->tiles()['dueSoon']);
    }

    public function test_packages_are_counted_by_status()
    {
        $this->addPackage('One');
        $this->addPackage('Two');
        $this->addPackage('Three', status: PackageStatus::Draft);

        $byStatus = ProjectAttention::for($this->project)->byStatus();

        $this->assertSame(['draft' => 1, 'planned' => 0, 'tendering' => 0, 'ordered' => 2, 'delivered' => 0, 'closed' => 0, 'cancelled' => 0], $byStatus);
    }

    public function test_open_packages_are_counted_per_category_and_level()
    {
        $electrical = ProjectCategory::factory()->for($this->project)->create(['code' => 'ELE']);
        $plenty = ['Required on site' => ['planned_date' => '2027-12-01', 'forecast_date' => '2027-06-01']];
        $this->addPackage('Fine cable', category: $electrical, dates: $plenty);
        $this->addPackage('Late cable', category: $electrical, dates: ['Required on site' => ['planned_date' => '2027-04-01', 'forecast_date' => '2027-05-01']]);
        $this->addPackage('Closed cable', category: $electrical, status: PackageStatus::Closed);
        $this->addPackage('Loose part');

        $byCategory = ProjectAttention::for($this->project)->riskByCategory();

        $this->assertSame([
            ['categoryId' => $electrical->id, 'red' => 1, 'amber' => 0, 'none' => 1],
            ['categoryId' => null, 'red' => 0, 'amber' => 1, 'none' => 0],
        ], $byCategory);
    }

    public function test_milestones_due_are_counted_per_week_from_this_week_on()
    {
        $this->addPackage('Pump', dates: [
            'Order placed' => ['forecast_date' => '2027-03-14'],             // Sunday of this week
            'Delivered from supplier' => ['forecast_date' => '2027-03-15'],  // Monday of next week
            'Required on site' => ['planned_date' => '2027-06-01'],          // week 12, out of the chart
        ]);
        $this->addPackage('Overdue valve', dates: ['Order placed' => ['planned_date' => '2027-03-09']]);
        $this->addPackage('Done tank', dates: ['Order placed' => ['planned_date' => '2027-03-11', 'actual_date' => '2027-03-01']]);

        $weeks = ProjectAttention::for($this->project)->milestonesDuePerWeek();

        $this->assertCount(ProjectAttention::CHART_WEEKS, $weeks);
        $this->assertSame(['weekStart' => '2027-03-08', 'count' => 1], $weeks[0]);
        $this->assertSame(['weekStart' => '2027-03-15', 'count' => 1], $weeks[1]);
        $this->assertSame(2, array_sum(array_column($weeks, 'count')));
    }

    public function test_a_persons_actions_follow_their_reminder_period()
    {
        $this->addPackage('Pump', dates: [
            'Order placed' => ['planned_date' => '2027-03-08'],               // overdue
            'Delivered from supplier' => ['forecast_date' => '2027-03-15'],   // 5 days left
            'Required on site' => ['planned_date' => '2027-04-30'],           // too far
        ]);
        $this->addPackage('Someone else’s', owner: null, buyer: null, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);
        $this->addPackage('Closed', status: PackageStatus::Closed, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);

        $attention = ProjectAttention::for($this->project);
        $actions = $attention->actionsFor($this->owner->id, 7);

        $this->assertSame(['overdue', 'coming_up'], array_column($actions, 'kind'));
        $this->assertSame(['Order placed', 'Delivered from supplier'], array_column($actions, 'milestoneName'));
        $this->assertSame([-2, 5], array_column($actions, 'daysLeft'));
        $this->assertTrue($actions[0]['asOwner']);
        $this->assertFalse($actions[0]['asBuyer']);
        $this->assertSame(['overdue'], array_column($attention->actionsFor($this->buyer->id, 0), 'kind'));
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_packages()
    {
        $this->addPackage('One', dates: ['Order placed' => ['planned_date' => '2027-03-08']]);
        $few = $this->countQueries(fn () => ProjectAttention::for($this->project->fresh())->tiles());

        foreach (range(1, 10) as $number) {
            $this->addPackage("More {$number}", dates: ['Order placed' => ['planned_date' => '2027-03-08']]);
        }

        $this->assertSame($few, $this->countQueries(fn () => ProjectAttention::for($this->project->fresh())->tiles()));
    }

    public function test_the_browser_test_project_has_one_package_behind_each_number()
    {
        // tests/Browser/support/test-data.ts (dashboardProject) expects exactly these.
        $this->seed(BrowserTestSeeder::class);
        $project = Project::where('code', 'DP9')->sole();
        $member = User::where('email', 'member@browser-test.example')->sole();
        $attention = ProjectAttention::for($project);

        $this->assertSame([
            'total' => 6, 'open' => 5, 'red' => 2, 'amber' => 2, 'overdue' => 1, 'dueSoon' => 1,
            'missingPeople' => 1, 'noNeedDate' => 1, 'flagged' => 1,
        ], $attention->tiles());
        $this->assertSame(['Overdue order', 'Coming delivery'], array_column($attention->actionsFor($member->id, $member->notification_days), 'title'));
    }

    /**
     * @param  array<string, array<string, string>>  $dates  per milestone name
     */
    private function addPackage(
        string $title,
        ?User $owner = new User,
        ?User $buyer = new User,
        PackageStatus $status = PackageStatus::Ordered,
        ?ProjectCategory $category = null,
        array $dates = [],
    ): Package {
        $package = app(CreatePackage::class)->handle($this->project->fresh(), $title, $category, null);
        $package->forceFill([
            'owner_id' => $owner === null ? null : $this->owner->id,
            'buyer_id' => $buyer === null ? null : $this->buyer->id,
            'status' => $status,
        ])->save();

        foreach ($dates as $milestoneName => $values) {
            $milestone = $this->project->milestones()->where('name', $milestoneName)->firstOrFail();
            PackageMilestone::factory()->for($package)->for($milestone)->create($values);
        }

        return $package;
    }

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
