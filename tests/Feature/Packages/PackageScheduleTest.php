<?php

namespace Tests\Feature\Packages;

use App\Actions\Packages\CreatePackage;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Scheduling\Days;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageScheduleTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_viewers_and_outsiders_cannot_open_or_save_the_dates()
    {
        $package = $this->addPackage('Transformer');
        $payload = ['milestones' => [$this->row('Order placed', forecast: '2027-01-10')]];

        foreach ([$this->member(ProjectRole::Viewer), User::factory()->create()] as $user) {
            $this->actingAs($user)->get($this->editUrl($package))->assertForbidden();
            $this->actingAs($user)->put($this->updateUrl($package), $payload)->assertForbidden();
        }

        $this->assertDatabaseCount('package_milestones', 0);
    }

    public function test_package_owners_edit_the_dates_of_their_own_packages_only()
    {
        $owner = $this->member(ProjectRole::PackageOwner);
        $own = $this->addPackage('Transformer');
        $own->owner_id = $owner->id;
        $own->save();
        $someoneElses = $this->addPackage('Cables');
        $payload = ['milestones' => [$this->row('Order placed', forecast: '2027-01-10')]];

        $this->actingAs($owner)->get($this->editUrl($own))->assertOk();
        $this->actingAs($owner)->put($this->updateUrl($own), $payload)->assertRedirect(route('projects.packages.show', [$this->project, $own]));
        $this->actingAs($owner)->get($this->editUrl($someoneElses))->assertForbidden();
        $this->actingAs($owner)->put($this->updateUrl($someoneElses), $payload)->assertForbidden();

        $this->assertDatabaseHas('package_milestones', ['package_id' => $own->id, 'forecast_date' => '2027-01-10']);
        $this->assertDatabaseMissing('package_milestones', ['package_id' => $someoneElses->id]);
    }

    public function test_the_edit_page_lists_the_projects_milestones_with_this_packages_values()
    {
        $package = $this->addPackage('Transformer');
        $this->setDates($package, 'Order placed', ['forecast_date' => '2027-01-10', 'lead_days' => 14]);

        $this->actingAs($this->manager)
            ->get($this->editUrl($package))
            ->assertInertia(fn ($page) => $page
                ->component('projects/packages/Schedule')
                ->where('package', ['id' => $package->id, 'number' => 'P100-ELE-0001', 'title' => 'Transformer'])
                ->has('milestones', 7)
                ->where('milestones.0.name', 'Specification ready')
                ->where('milestones.3.name', 'Order placed')
                ->where('milestones.3.forecastDate', '2027-01-10')
                ->where('milestones.3.leadDays', 14)
                ->where('milestones.3.plannedDate', null)
                ->where('milestones.5.isNeedDate', true)
                ->where('canChangePlannedDates', true)
                ->where('today', Days::latestToday()));
    }

    public function test_dates_are_saved_and_milestones_left_out_keep_theirs()
    {
        $package = $this->addPackage('Transformer');
        $this->setDates($package, 'Specification ready', ['actual_date' => '2026-09-01']);

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [
                $this->row('Order placed', planned: '2027-01-15', forecast: '2027-01-10', lead: 30),
                $this->row('Required on site', planned: '2027-06-30', lead: 30),
            ]])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Dates saved.');

        $this->assertSame(3, $package->milestones()->count());
        $this->assertDatabaseHas('package_milestones', [
            'package_id' => $package->id,
            'project_milestone_id' => $this->milestoneId('Order placed'),
            'planned_date' => '2027-01-15',
            'forecast_date' => '2027-01-10',
            'actual_date' => null,
            'lead_days' => 30,
        ]);
        $this->assertDatabaseHas('package_milestones', ['project_milestone_id' => $this->milestoneId('Specification ready'), 'actual_date' => '2026-09-01']);
    }

    public function test_rows_that_become_empty_are_deleted()
    {
        $package = $this->addPackage('Transformer');
        $this->setDates($package, 'Order placed', ['forecast_date' => '2027-01-10', 'lead_days' => 30]);

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Order placed'), $this->row('Offers received')]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('package_milestones', 0);
    }

    public function test_the_first_milestone_has_no_lead_time()
    {
        $package = $this->addPackage('Transformer');

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [
                $this->row('Specification ready', lead: 10),
                $this->row('Request for quotation sent', lead: 10),
            ]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('package_milestones', ['project_milestone_id' => $this->milestoneId('Specification ready')]);
        $this->assertDatabaseHas('package_milestones', ['project_milestone_id' => $this->milestoneId('Request for quotation sent'), 'lead_days' => 10]);
    }

    public function test_a_milestone_of_another_project_is_refused()
    {
        $package = $this->addPackage('Transformer');
        $otherMilestone = ProjectMilestone::factory()->create();

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [
                ['id' => $otherMilestone->id, 'planned_date' => '2027-01-01', 'forecast_date' => null, 'actual_date' => null, 'lead_days' => null],
            ]])
            ->assertSessionHasErrors(['milestones.0.id' => 'This milestone is not part of the project.']);

        $this->assertDatabaseCount('package_milestones', 0);
    }

    public function test_the_same_milestone_cannot_be_sent_twice()
    {
        $package = $this->addPackage('Transformer');

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [
                $this->row('Order placed', forecast: '2027-01-10'),
                $this->row('Order placed', forecast: '2027-02-10'),
            ]])
            ->assertSessionHasErrors(['milestones.0.id', 'milestones.1.id']);
    }

    public function test_buyers_fill_in_empty_planned_dates_but_cannot_change_or_clear_set_ones()
    {
        $buyer = $this->member(ProjectRole::Buyer);
        $package = $this->addPackage('Transformer');
        $this->setDates($package, 'Required on site', ['planned_date' => '2027-06-30']);
        $message = 'Only a project manager can change a planned date once it is set.';

        $this->actingAs($buyer)->get($this->editUrl($package))->assertInertia(fn ($page) => $page->where('canChangePlannedDates', false));

        $this->actingAs($buyer)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Required on site', planned: '2027-07-31')]])
            ->assertSessionHasErrors(['milestones.0.planned_date' => $message]);
        $this->actingAs($buyer)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Required on site', planned: null)]])
            ->assertSessionHasErrors(['milestones.0.planned_date' => $message]);

        $this->actingAs($buyer)
            ->put($this->updateUrl($package), ['milestones' => [
                $this->row('Order placed', planned: '2027-01-15'),
                $this->row('Required on site', planned: '2027-06-30', forecast: '2027-06-20'),
            ]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('package_milestones', ['project_milestone_id' => $this->milestoneId('Order placed'), 'planned_date' => '2027-01-15']);
        $this->assertDatabaseHas('package_milestones', [
            'project_milestone_id' => $this->milestoneId('Required on site'),
            'planned_date' => '2027-06-30',
            'forecast_date' => '2027-06-20',
        ]);
    }

    public function test_project_managers_and_admins_change_and_clear_set_planned_dates()
    {
        $package = $this->addPackage('Transformer');
        $this->setDates($package, 'Required on site', ['planned_date' => '2027-06-30']);
        $this->setDates($package, 'Order placed', ['planned_date' => '2027-01-15']);

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Required on site', planned: '2027-07-31')]])
            ->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->admin()->create())
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Order placed', planned: null)]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('package_milestones', ['project_milestone_id' => $this->milestoneId('Required on site'), 'planned_date' => '2027-07-31']);
        $this->assertDatabaseMissing('package_milestones', ['project_milestone_id' => $this->milestoneId('Order placed')]);
    }

    public function test_an_actual_date_cannot_be_in_the_future()
    {
        $package = $this->addPackage('Transformer');
        $tomorrow = Days::add(Days::latestToday(), 1);

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Order placed', actual: $tomorrow)]])
            ->assertSessionHasErrors(['milestones.0.actual_date' => 'An actual date can’t be in the future.']);

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Order placed', actual: Days::latestToday(), forecast: $tomorrow)]])
            ->assertSessionHasNoErrors();
    }

    public function test_todays_actual_date_is_accepted_shortly_after_midnight_east_of_utc()
    {
        $package = $this->addPackage('Transformer');
        // 01:30 on 2 January in Finland.
        $this->travelTo(now('UTC')->setDate(2027, 1, 1)->setTime(23, 30));

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [$this->row('Order placed', actual: '2027-01-02')]])
            ->assertSessionHasNoErrors();
    }

    public function test_invalid_dates_and_lead_times_are_refused()
    {
        $package = $this->addPackage('Transformer');

        $this->actingAs($this->manager)
            ->put($this->updateUrl($package), ['milestones' => [
                $this->row('Request for quotation sent', planned: '1999-12-31', lead: 3651),
                $this->row('Offers received', forecast: '2101-01-01', lead: -1),
                $this->row('Order placed', planned: '15.01.2027', lead: 2),
            ]])
            ->assertSessionHasErrors([
                'milestones.0.planned_date' => 'Enter a date between the years 2000 and 2100.',
                'milestones.0.lead_days' => 'Enter a lead time between 0 and 3650 days.',
                'milestones.1.forecast_date' => 'Enter a date between the years 2000 and 2100.',
                'milestones.1.lead_days' => 'Enter a lead time between 0 and 3650 days.',
                'milestones.2.planned_date' => 'Enter a date between the years 2000 and 2100.',
            ]);

        $this->assertDatabaseCount('package_milestones', 0);
    }

    public function test_the_package_page_shows_the_calculated_schedule()
    {
        $package = $this->addPackage('Transformer');
        $this->setDates($package, 'Order placed', ['forecast_date' => '2027-01-10']);
        $this->setDates($package, 'Delivered from supplier', ['lead_days' => 120]);
        $this->setDates($package, 'Required on site', ['planned_date' => '2027-06-30', 'lead_days' => 30]);
        $this->setDates($package, 'Specification ready', ['actual_date' => '2026-09-01']);

        $this->actingAs($this->member(ProjectRole::Viewer))
            ->get(route('projects.packages.show', [$this->project, $package]))
            ->assertInertia(fn ($page) => $page
                ->has('schedule.milestones', 7)
                ->where('schedule.milestones.0.name', 'Specification ready')
                ->where('schedule.milestones.0.isDone', true)
                ->where('schedule.milestones.3.forecast', '2027-01-10')
                ->where('schedule.milestones.3.expected', '2027-01-10')
                ->where('schedule.milestones.3.latest', '2027-01-31')
                ->where('schedule.milestones.4.leadDays', 120)
                ->where('schedule.milestones.4.expected', '2027-05-10')
                ->where('schedule.milestones.4.latest', '2027-05-31')
                ->where('schedule.milestones.5.isNeedDate', true)
                ->where('schedule.milestones.5.planned', '2027-06-30')
                ->where('schedule.milestones.5.expected', '2027-06-09')
                ->where('schedule.milestones.5.latest', '2027-06-30')
                ->where('schedule.milestones.5.expectedWithSubPackages', null)
                ->where('schedule.needDate', '2027-06-30')
                ->where('schedule.expectedNeedDate', '2027-06-09')
                ->where('schedule.floatDays', 21)
                ->where('schedule.withSubPackages', null));
    }

    public function test_a_parent_shows_the_latest_expected_dates_of_its_sub_packages_without_cancelled_ones()
    {
        $parent = $this->addPackage('Transformer');
        $child = $this->addPackage('Core', $parent);
        $grandchild = $this->addPackage('Bolts', $child);
        $cancelled = $this->addPackage('Old design', $parent);
        $cancelled->status = PackageStatus::Cancelled;
        $cancelled->save();
        $unrelated = $this->addPackage('Cables');

        $this->setDates($parent, 'Required on site', ['planned_date' => '2027-06-30', 'forecast_date' => '2027-06-01']);
        $this->setDates($child, 'Required on site', ['forecast_date' => '2027-06-10']);
        $this->setDates($child, 'Order placed', ['actual_date' => '2026-10-01']);
        $this->setDates($grandchild, 'Required on site', ['forecast_date' => '2027-07-05']);
        $this->setDates($cancelled, 'Required on site', ['forecast_date' => '2027-09-01']);
        $this->setDates($unrelated, 'Required on site', ['forecast_date' => '2028-01-01']);

        $this->actingAs($this->manager)
            ->get(route('projects.packages.show', [$this->project, $parent]))
            ->assertInertia(fn ($page) => $page
                ->where('schedule.milestones.3.expected', null)
                ->where('schedule.milestones.3.expectedWithSubPackages', '2026-10-01')
                ->where('schedule.milestones.5.expected', '2027-06-01')
                ->where('schedule.milestones.5.expectedWithSubPackages', '2027-07-05')
                ->where('schedule.floatDays', 29)
                ->where('schedule.withSubPackages', ['expectedNeedDate' => '2027-07-05', 'floatDays' => -5]));

        $this->actingAs($this->manager)
            ->get(route('projects.packages.show', [$this->project, $child]))
            ->assertInertia(fn ($page) => $page
                ->where('schedule.milestones.5.expectedWithSubPackages', '2027-07-05')
                ->where('schedule.withSubPackages', ['expectedNeedDate' => '2027-07-05', 'floatDays' => null]));
    }

    public function test_the_package_page_uses_the_same_number_of_queries_for_any_number_of_sub_packages()
    {
        $parent = $this->addPackage('Transformer');
        $this->addSubPackageWithDates($parent, 'Part 0');

        $queriesForOne = $this->countQueries(fn () => $this->actingAs($this->manager->fresh())->get(route('projects.packages.show', [$this->project, $parent])));

        foreach (range(1, 10) as $count) {
            $this->addSubPackageWithDates($this->addSubPackageWithDates($parent, "Part {$count}"), "Piece {$count}");
        }

        $queriesForMany = $this->countQueries(fn () => $this->actingAs($this->manager->fresh())->get(route('projects.packages.show', [$this->project, $parent])));

        $this->assertSame($queriesForOne, $queriesForMany);
    }

    private function addSubPackageWithDates(Package $parent, string $title): Package
    {
        $package = $this->addPackage($title, $parent);
        $this->setDates($package, 'Order placed', ['forecast_date' => '2027-01-10']);
        $this->setDates($package, 'Required on site', ['lead_days' => 60]);

        return $package;
    }

    private function addPackage(string $title, ?Package $parent = null): Package
    {
        return app(CreatePackage::class)->handle($this->project->fresh(), $title, $this->electrical, $parent);
    }

    /**
     * @param  array<string, string|int|null>  $values
     */
    private function setDates(Package $package, string $milestone, array $values): void
    {
        PackageMilestone::factory()->for($package)->create(['project_milestone_id' => $this->milestoneId($milestone), ...$values]);
    }

    private function milestoneId(string $name): int
    {
        return $this->project->milestones()->where('name', $name)->value('id');
    }

    /**
     * @return array{id: int, planned_date: string|null, forecast_date: string|null, actual_date: string|null, lead_days: int|null}
     */
    private function row(string $milestone, ?string $planned = null, ?string $forecast = null, ?string $actual = null, ?int $lead = null): array
    {
        return [
            'id' => $this->milestoneId($milestone),
            'planned_date' => $planned,
            'forecast_date' => $forecast,
            'actual_date' => $actual,
            'lead_days' => $lead,
        ];
    }

    private function member(ProjectRole $role): User
    {
        $user = User::factory()->create();
        $membership = $this->project->members()->make(['role' => $role]);
        $membership->user_id = $user->id;
        $membership->save();

        return $user;
    }

    private function editUrl(Package $package): string
    {
        return route('projects.packages.schedule.edit', [$this->project, $package]);
    }

    private function updateUrl(Package $package): string
    {
        return route('projects.packages.schedule.update', [$this->project, $package]);
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
