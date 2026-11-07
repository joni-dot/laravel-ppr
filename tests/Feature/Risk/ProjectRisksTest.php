<?php

namespace Tests\Feature\Risk;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\User;
use App\Risk\ProjectRisks;
use App\Risk\RiskLevel;
use App\Risk\RiskReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectRisksTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $owner;

    private User $buyer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
        $this->manager = User::factory()->create(['name' => 'Mia Manager']);
        $this->owner = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->withMember($this->owner, ProjectRole::PackageOwner)
            ->withMember($this->buyer, ProjectRole::Buyer)
            ->create(['number_includes_category' => false, 'risk_amber_days' => 14, 'risk_red_days' => 0]);
    }

    public function test_a_package_with_only_a_planned_need_date_has_no_days_to_spare()
    {
        $package = $this->addPackage('Transformer');
        // With nothing else filled in, it is expected exactly on its need date, which is below the amber warning.
        $this->fillIn($package, 'Required on site', ['planned_date' => '2027-06-01']);

        $risk = ProjectRisks::for($this->project)[$package->id];

        $this->assertSame(RiskLevel::Amber, $risk->level);
        $this->assertSame([RiskReason::Late], $risk->reasons);
    }

    public function test_a_package_late_because_of_its_sub_package_is_red()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer);
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01']);
        // Its own need date is far off, but the transformer can't be ready before the core is.
        $this->fillIn($core, 'Required on site', ['planned_date' => '2027-12-01', 'forecast_date' => '2027-06-05']);

        $risks = ProjectRisks::for($this->project);

        $this->assertSame(RiskLevel::Red, $risks[$transformer->id]->level);
        $this->assertSame([RiskReason::Late], $risks[$transformer->id]->reasons);
        $this->assertSame(RiskLevel::None, $risks[$core->id]->level);
    }

    public function test_a_cancelled_sub_package_does_not_make_its_parent_late()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer);
        $core->forceFill(['status' => PackageStatus::Cancelled])->save();
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $this->fillIn($core, 'Required on site', ['planned_date' => '2027-12-01', 'forecast_date' => '2027-06-05']);

        $this->assertSame(RiskLevel::None, ProjectRisks::for($this->project)[$transformer->id]->level);
    }

    public function test_only_the_float_rolls_up_from_sub_packages()
    {
        $transformer = $this->addPackage('Transformer');
        $core = $this->addPackage('Core', $transformer, buyer: null);
        $core->forceFill(['risk_flag_reason' => 'Supplier went bankrupt', 'risk_flagged_at' => now()])->save();
        $this->fillIn($transformer, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $this->fillIn($core, 'Order placed', ['planned_date' => '2027-02-01']);

        $risks = ProjectRisks::for($this->project);

        $this->assertSame(RiskLevel::None, $risks[$transformer->id]->level);
        $this->assertSame([RiskReason::ManualFlag, RiskReason::Overdue, RiskReason::NoNeedDate, RiskReason::NoBuyer], $risks[$core->id]->reasons);
    }

    public function test_deactivated_people_and_flags_are_read_from_the_database()
    {
        $package = $this->addPackage('Transformer');
        $this->fillIn($package, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);
        $this->owner->forceFill(['deactivated_at' => now()])->save();

        $this->assertSame([RiskReason::OwnerDeactivated], ProjectRisks::for($this->project)[$package->id]->reasons);

        $package->forceFill(['risk_flag_reason' => 'Supplier went bankrupt', 'risk_flagged_at' => now()])->save();

        $this->assertSame(RiskLevel::Red, ProjectRisks::for($this->project)[$package->id]->level);
    }

    public function test_overdue_counts_from_today_in_utc()
    {
        $package = $this->addPackage('Transformer');
        $this->fillIn($package, 'Order placed', ['planned_date' => '2027-03-10']);
        $this->fillIn($package, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-05-01']);

        $this->assertSame(RiskLevel::None, ProjectRisks::for($this->project)[$package->id]->level);

        // Just after midnight in UTC, the order date has passed everywhere the day has ended.
        $this->travelTo('2027-03-11 00:05:00');

        $risk = ProjectRisks::for($this->project)[$package->id];
        $this->assertSame([RiskReason::Overdue], $risk->reasons);
        $this->assertSame('2027-03-10', $risk->overdueMilestone?->expected);
    }

    public function test_a_whole_project_takes_the_same_queries_however_many_packages_it_has()
    {
        $this->addPackage('First');
        $queriesForOne = $this->countQueries(fn () => ProjectRisks::for($this->project));

        foreach (range(1, 10) as $count) {
            $package = $this->addPackage("Package {$count}", owner: null);
            $sub = $this->addPackage("Part {$count}", $package);
            $this->fillIn($sub, 'Order placed', ['planned_date' => '2027-01-01']);
            $this->fillIn($package, 'Required on site', ['planned_date' => '2027-06-01', 'forecast_date' => '2027-07-01']);
        }

        $this->assertSame($queriesForOne, $this->countQueries(fn () => ProjectRisks::for($this->project)));
        $this->assertSame(3, $queriesForOne);
    }

    private function addPackage(string $title, ?Package $parent = null, ?User $owner = new User, ?User $buyer = new User): Package
    {
        $package = app(CreatePackage::class)->handle($this->project->fresh(), $title, null, $parent);
        $package->forceFill([
            'owner_id' => $owner === null ? null : $this->owner->id,
            'buyer_id' => $buyer === null ? null : $this->buyer->id,
            'status' => PackageStatus::Ordered,
        ])->save();

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

    private function countQueries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
