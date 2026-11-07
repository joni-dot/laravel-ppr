<?php

namespace Tests\Feature\Packages;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * How the package list and the package page show risk. The rules themselves are tested in Tests\Unit\Risk.
 */
class PackageRiskPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->manager = User::factory()->create(['name' => 'Mia Manager']);
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->create(['number_includes_category' => false, 'risk_amber_days' => 14, 'risk_red_days' => 0]);
    }

    public function test_the_list_sends_risk_only_for_packages_at_risk_with_details_only_where_needed()
    {
        $fine = $this->addPackage('Fine');
        $this->fillIn($fine, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $overdue = $this->addPackage('Overdue');
        $this->fillIn($overdue, 'Order placed', ['planned_date' => '2027-03-01']);
        $this->fillIn($overdue, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $flagged = $this->addPackage('Flagged', withPeople: false);
        $flagged->forceFill(['risk_flag_reason' => 'Supplier went bankrupt', 'risk_flagged_at' => now()])->save();

        $this->actingAs($this->manager)
            ->get(route('projects.packages.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('packages.0.risk', null)
                ->where('packages.1.risk', ['level' => 'red', 'reasons' => ['overdue'], 'overdueMilestone' => 'Order placed'])
                ->where('packages.2.risk', [
                    'level' => 'red',
                    'reasons' => ['manual_flag', 'no_need_date', 'no_owner', 'no_buyer'],
                    'flagReason' => 'Supplier went bankrupt',
                ]));
    }

    public function test_a_closed_package_shows_no_risk_even_when_it_is_flagged()
    {
        $package = $this->addPackage('Done', withPeople: false);
        $package->forceFill(['status' => PackageStatus::Closed, 'risk_flag_reason' => 'Old worry', 'risk_flagged_at' => now()])->save();

        $this->actingAs($this->manager)
            ->get(route('projects.packages.index', $this->project))
            ->assertInertia(fn ($page) => $page->where('packages.0.risk', null));

        $this->actingAs($this->manager)
            ->get(route('projects.packages.show', [$this->project, $package]))
            ->assertInertia(fn ($page) => $page
                ->where('risk.level', 'none')
                ->where('risk.reasons', [])
                // Still shown, so the people who may edit it can clear it.
                ->where('risk.flag.reason', 'Old worry'));
    }

    public function test_the_package_page_explains_each_reason()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer);
        $this->fillIn($transformer, 'Order placed', ['planned_date' => '2027-03-03']);
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01']);
        $this->fillIn($core, 'Required on site', ['forecast_date' => '2027-06-13']);
        $transformer->forceFill([
            'risk_flag_reason' => 'Supplier went bankrupt',
            'risk_flagged_by_id' => $this->manager->id,
            'risk_flagged_at' => now(),
        ])->save();

        $this->actingAs($this->manager)
            ->get(route('projects.packages.show', [$this->project, $transformer]))
            ->assertInertia(fn ($page) => $page
                ->where('risk', [
                    'level' => 'red',
                    'reasons' => ['manual_flag', 'overdue', 'late'],
                    // Counting the core, which is expected 12 days after the transformer's need date.
                    'floatDays' => -12,
                    'thresholds' => ['amberDays' => 14, 'redDays' => 0],
                    'overdueMilestone' => ['name' => 'Order placed', 'expected' => '2027-03-03'],
                    'flag' => [
                        'reason' => 'Supplier went bankrupt',
                        'flaggedByName' => 'Mia Manager',
                        'flaggedAt' => '2027-03-10T12:00:00+00:00',
                    ],
                ]));
    }

    public function test_the_list_uses_the_same_number_of_queries_however_many_packages_are_at_risk()
    {
        $this->addPackage('First');
        $queriesForOne = $this->countListQueries();
        $deactivated = User::factory()->create(['deactivated_at' => now()]);

        foreach (range(1, 10) as $count) {
            $package = $this->addPackage("Package {$count}");
            $package->forceFill(['owner_id' => $deactivated->id, 'risk_flag_reason' => 'Worried', 'risk_flagged_at' => now()])->save();
            $sub = $this->addPackage("Part {$count}", $package, withPeople: false);
            $this->fillIn($sub, 'Order placed', ['planned_date' => '2027-01-01']);
            $this->fillIn($package, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-07-01']);
        }

        $this->assertSame($queriesForOne, $this->countListQueries());
    }

    private function addPackage(string $title, ?Package $parent = null, bool $withPeople = true): Package
    {
        $package = app(CreatePackage::class)->handle($this->project->fresh(), $title, null, $parent);

        if ($withPeople) {
            $package->forceFill(['owner_id' => $this->manager->id, 'buyer_id' => $this->manager->id])->save();
        }

        return $package;
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
        $this->actingAs($manager)->get(route('projects.packages.index', $this->project))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
