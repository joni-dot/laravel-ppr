<?php

namespace Tests\Feature;

use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Http\Controllers\DashboardController;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2027-03-10 12:00:00');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_someone_in_no_project_sees_no_projects()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('projects', [])
                ->where('can.seeAllProjects', false)
                ->missing('attention')
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('attention', ['tiles' => [], 'actions' => [], 'actionsTotal' => 0])));
    }

    public function test_an_admin_sees_only_the_projects_they_are_a_member_of()
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'Someone else’s']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('projects', [])
                ->where('can.seeAllProjects', true));
    }

    #[DataProvider('roles')]
    public function test_every_member_sees_their_own_projects_and_their_numbers(ProjectRole $role)
    {
        // Given, because a new model doesn't know the column's database default, unlike a signed-in user.
        $person = User::factory()->create(['notification_days' => 7]);
        $owner = User::factory()->create();
        $mine = Project::factory()->withDefaultMilestones()->withMember($person, $role)->withMember($owner, ProjectRole::PackageOwner)
            ->create(['name' => 'Bridge', 'code' => 'BR1']);
        $alsoMine = Project::factory()->withMember($person, ProjectRole::Viewer)->create(['name' => 'Airport', 'code' => 'AP2']);
        Project::factory()->withMember($owner, ProjectRole::ProjectManager)->create(['name' => 'Hidden', 'code' => 'HI3']);
        $this->addPackage($mine, owner: $owner, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);

        $this->actingAs($person)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('projects', [
                    ['id' => $alsoMine->id, 'name' => 'Airport', 'code' => 'AP2', 'role' => 'Viewer'],
                    ['id' => $mine->id, 'name' => 'Bridge', 'code' => 'BR1', 'role' => $role->label()],
                ])
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('attention.tiles.0.projectId', $alsoMine->id)
                    ->where('attention.tiles.1.projectId', $mine->id)
                    ->where('attention.tiles.1.tiles.total', 1)
                    ->where('attention.tiles.1.tiles.overdue', 1)
                    ->where('attention.tiles.1.tiles.red', 1)
                    ->has('attention.tiles', 2)
                    ->where('attention.actions', [])));
    }

    /**
     * @return array<string, array{ProjectRole}>
     */
    public static function roles(): array
    {
        return array_combine(
            array_map(fn (ProjectRole $role) => $role->value, ProjectRole::cases()),
            array_map(fn (ProjectRole $role) => [$role], ProjectRole::cases()),
        );
    }

    public function test_actions_come_only_from_packages_the_person_owns_or_buys_earliest_first()
    {
        $person = User::factory()->create(['notification_days' => 7]);
        $other = User::factory()->create();
        $first = Project::factory()->withDefaultMilestones()->withMember($person, ProjectRole::Buyer)->create(['name' => 'A', 'code' => 'AA1']);
        $second = Project::factory()->withDefaultMilestones()->withMember($person, ProjectRole::PackageOwner)->create(['name' => 'B', 'code' => 'BB2']);

        $comingUp = $this->addPackage($first, buyer: $person, title: 'Coming pump', dates: ['Order placed' => ['forecast_date' => '2027-03-15']]);
        $overdue = $this->addPackage($second, owner: $person, title: 'Late valve', dates: ['Order placed' => ['planned_date' => '2027-03-05']]);
        $both = $this->addPackage($first, owner: $person, buyer: $person, title: 'Today tank', dates: ['Order placed' => ['forecast_date' => '2027-03-10']]);
        $this->addPackage($first, owner: $other, buyer: $other, title: 'Not mine', dates: ['Order placed' => ['planned_date' => '2027-03-05']]);
        $this->addPackage($second, owner: $person, title: 'Too far', dates: ['Order placed' => ['forecast_date' => '2027-03-20']]);
        $this->addPackage($second, owner: $person, title: 'Closed', status: PackageStatus::Closed, dates: ['Order placed' => ['planned_date' => '2027-03-05']]);

        $this->actingAs($person)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notificationDays', 7)
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->where('attention.actionsTotal', 3)
                    ->where('attention.actions.0', [
                        'projectId' => $second->id,
                        'projectCode' => 'BB2',
                        'packageId' => $overdue->id,
                        'number' => $overdue->number,
                        'title' => 'Late valve',
                        'milestoneName' => 'Order placed',
                        'date' => '2027-03-05',
                        'daysLeft' => -5,
                        'kind' => 'overdue',
                        'asOwner' => true,
                        'asBuyer' => false,
                    ])
                    ->where('attention.actions.1.packageId', $both->id)
                    ->where('attention.actions.1.kind', 'coming_up')
                    ->where('attention.actions.1.asOwner', true)
                    ->where('attention.actions.1.asBuyer', true)
                    ->where('attention.actions.2.packageId', $comingUp->id)
                    ->where('attention.actions.2.projectId', $first->id)
                    ->where('attention.actions.2.asOwner', false)
                    ->where('attention.actions.2.asBuyer', true)));
    }

    public function test_at_most_fifty_actions_are_sent_with_the_total()
    {
        $person = User::factory()->create(['notification_days' => 7]);
        $project = Project::factory()->withDefaultMilestones()->withMember($person, ProjectRole::PackageOwner)->create();
        $milestone = $project->milestones()->where('name', 'Order placed')->sole();

        foreach (range(1, DashboardController::ACTIONS_SHOWN + 1) as $number) {
            $package = Package::factory()->for($project)->create(['owner_id' => $person->id]);
            PackageMilestone::factory()->for($package)->for($milestone)->create(['planned_date' => '2027-03-01']);
        }

        $this->actingAs($person)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                    ->has('attention.actions', DashboardController::ACTIONS_SHOWN)
                    ->where('attention.actionsTotal', DashboardController::ACTIONS_SHOWN + 1)));
    }

    public function test_the_query_count_grows_with_projects_but_not_with_packages()
    {
        $person = User::factory()->create(['notification_days' => 7]);
        $first = Project::factory()->withDefaultMilestones()->withMember($person, ProjectRole::PackageOwner)->create();
        $this->addPackage($first, owner: $person, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);
        $oneProject = $this->countDashboardQueries($person);

        foreach (range(1, 10) as $number) {
            $this->addPackage($first, owner: $person, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);
        }
        $this->assertSame($oneProject, $this->countDashboardQueries($person));

        $second = Project::factory()->withDefaultMilestones()->withMember($person, ProjectRole::PackageOwner)->create();
        $this->addPackage($second, owner: $person, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);
        $twoProjects = $this->countDashboardQueries($person);

        $third = Project::factory()->withDefaultMilestones()->withMember($person, ProjectRole::PackageOwner)->create();
        $this->addPackage($third, owner: $person, dates: ['Order placed' => ['planned_date' => '2027-03-08']]);

        $this->assertGreaterThan($oneProject, $twoProjects);
        $this->assertSame($twoProjects - $oneProject, $this->countDashboardQueries($person) - $twoProjects);
    }

    /**
     * @param  array<string, array<string, string>>  $dates  per milestone name
     */
    private function addPackage(
        Project $project,
        ?User $owner = null,
        ?User $buyer = null,
        string $title = 'Pump',
        PackageStatus $status = PackageStatus::Ordered,
        array $dates = [],
    ): Package {
        $package = Package::factory()->for($project)->create([
            'title' => $title,
            'owner_id' => $owner?->id,
            'buyer_id' => $buyer?->id,
            'status' => $status,
        ]);

        foreach ($dates as $milestoneName => $values) {
            $milestone = $project->milestones()->where('name', $milestoneName)->sole();
            PackageMilestone::factory()->for($package)->for($milestone)->create($values);
        }

        return $package;
    }

    // Both requests: the page, and the one that loads its deferred numbers.
    private function countDashboardQueries(User $person): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($person)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps(fn (AssertableInertia $reload) => $reload->has('attention')));
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
