<?php

namespace Tests\Feature\Packages;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\LoadsPackageTree;
use Tests\TestCase;

class PackageListScheduleTest extends TestCase
{
    use LoadsPackageTree, RefreshDatabase;

    private User $manager;

    private Project $project;

    private ProjectCategory $electrical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->create(['code' => 'P100']);
        $this->electrical = ProjectCategory::factory()->for($this->project)->create(['code' => 'ELE']);
    }

    public function test_each_row_shows_the_next_milestone_the_need_date_and_the_float()
    {
        $transformer = $this->addPackage('Transformer');
        $this->addPackage('Cables');
        $this->fillIn($transformer, 'Specification ready', ['actual_date' => '2027-01-05']);
        $this->fillIn($transformer, 'Request for quotation sent', ['planned_date' => '2027-01-12', 'lead_days' => 10]);
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-20']);

        $this->actingAs($this->manager)
            ->getPackageTree($this->project)
            ->assertInertia(fn ($page) => $page
                ->where('packages.0.nextMilestoneName', 'Request for quotation sent')
                ->where('packages.0.nextMilestoneDate', '2027-01-15')
                ->where('packages.0.needDate', '2027-06-01')
                ->where('packages.0.expectedNeedDate', '2027-05-20')
                ->where('packages.0.floatDays', 12)
                ->where('packages.1.nextMilestoneName', 'Specification ready')
                ->where('packages.1.nextMilestoneDate', null)
                ->where('packages.1.needDate', null)
                ->where('packages.1.expectedNeedDate', null)
                ->where('packages.1.floatDays', null));
    }

    public function test_a_package_is_expected_when_the_last_package_below_it_is()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer);
        $bolts = $this->addPackage('Bolts', $core);
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $this->fillIn($core, 'Required on site', ['forecast_date' => '2027-05-15']);
        $this->fillIn($bolts, 'Required on site', ['planned_date' => '2027-07-01', 'forecast_date' => '2027-06-11']);

        $this->actingAs($this->manager)
            ->getPackageTree($this->project)
            ->assertInertia(fn ($page) => $page
                ->where('packages.0.needDate', '2027-06-01')
                ->where('packages.0.expectedNeedDate', '2027-06-11')
                ->where('packages.0.floatDays', -10)
                ->where('packages.1.needDate', null)
                ->where('packages.1.expectedNeedDate', '2027-06-11')
                ->where('packages.1.floatDays', null)
                ->where('packages.2.needDate', '2027-07-01')
                ->where('packages.2.expectedNeedDate', '2027-06-11')
                ->where('packages.2.floatDays', 20));
    }

    public function test_a_cancelled_sub_package_does_not_hold_up_its_parent()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer);
        $core->forceFill(['status' => PackageStatus::Cancelled])->save();
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $this->fillIn($core, 'Required on site', ['forecast_date' => '2027-09-01']);

        $this->actingAs($this->manager)
            ->getPackageTree($this->project)
            ->assertInertia(fn ($page) => $page
                ->where('packages.0.expectedNeedDate', '2027-05-01')
                ->where('packages.0.floatDays', 31)
                ->where('packages.1.expectedNeedDate', '2027-09-01'));
    }

    public function test_the_list_uses_the_same_number_of_queries_however_many_packages_and_dates_there_are()
    {
        $this->addPackage('First');
        $queriesForOne = $this->countListQueries();

        foreach (range(1, 10) as $count) {
            $package = $this->addPackage("Package {$count}");
            $subPackage = $this->addPackage("Part {$count}", $package);

            foreach ([$package, $subPackage] as $withDates) {
                $this->fillIn($withDates, 'Specification ready', ['actual_date' => '2027-01-05']);
                $this->fillIn($withDates, 'Order placed', ['planned_date' => '2027-03-01', 'lead_days' => 20]);
                $this->fillIn($withDates, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-20']);
            }
        }

        $this->assertSame($queriesForOne, $this->countListQueries());
    }

    private function addPackage(string $title, ?Package $parent = null): Package
    {
        return app(CreatePackage::class)->handle($this->project->fresh(), $title, $this->electrical, $parent);
    }

    /**
     * @param  array<string, string|int>  $values
     */
    private function fillIn(Package $package, string $milestoneName, array $values): void
    {
        $milestone = $this->project->milestones()->where('name', $milestoneName)->firstOrFail();

        PackageMilestone::factory()->for($package)->for($milestone)->create($values);
    }

    private function countListQueries(): int
    {
        $manager = $this->manager->fresh();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->getPackageTree($this->project)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
