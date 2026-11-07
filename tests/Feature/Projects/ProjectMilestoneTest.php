<?php

namespace Tests\Feature\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\PackageMilestone;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->project = Project::factory()
            ->withDefaultMilestones()
            ->withMember($this->manager, ProjectRole::ProjectManager)
            ->create();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function allMilestoneRoutes(): array
    {
        return [
            'list' => ['get', 'projects.milestones.index'],
            'add' => ['post', 'projects.milestones.store'],
            'save' => ['put', 'projects.milestones.update'],
            'remove' => ['delete', 'projects.milestones.destroy'],
            'move' => ['patch', 'projects.milestones.move'],
        ];
    }

    /**
     * @return array<string, array{ProjectRole}>
     */
    public static function rolesBelowManager(): array
    {
        return [
            'buyer' => [ProjectRole::Buyer],
            'package owner' => [ProjectRole::PackageOwner],
            'viewer' => [ProjectRole::Viewer],
        ];
    }

    #[DataProvider('rolesBelowManager')]
    public function test_only_project_managers_and_admins_can_change_milestones(ProjectRole $role)
    {
        $user = User::factory()->create();
        $this->project->members()->make(['role' => $role])->user()->associate($user)->save();

        foreach (self::allMilestoneRoutes() as [$method, $route]) {
            $this->actingAs($user)
                ->call($method, $this->url($route), ['name' => 'Sneaky', 'is_need_date' => true, 'direction' => 'up'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('project_milestones', ['name' => 'Sneaky']);
        $this->assertSame($this->defaultNames(), $this->names());
    }

    #[DataProvider('allMilestoneRoutes')]
    public function test_people_outside_the_project_get_nothing(string $method, string $route)
    {
        $this->actingAs(User::factory()->create())
            ->call($method, $this->url($route), ['name' => 'Sneaky', 'direction' => 'up'])
            ->assertForbidden();
    }

    public function test_admins_can_change_milestones_in_any_project()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('projects.milestones.store', $this->project), ['name' => 'Tested'])
            ->assertSessionHasNoErrors();

        $this->assertContains('Tested', $this->names());
    }

    public function test_a_milestone_of_another_project_cannot_be_reached_through_this_project()
    {
        $other = ProjectMilestone::factory()->create(['name' => 'Elsewhere']);

        foreach (['put' => 'projects.milestones.update', 'delete' => 'projects.milestones.destroy', 'patch' => 'projects.milestones.move'] as $method => $route) {
            $this->actingAs($this->manager)
                ->call($method, route($route, [$this->project, $other]), ['name' => 'Changed', 'direction' => 'up'])
                ->assertNotFound();
        }

        $this->assertSame('Elsewhere', $other->refresh()->name);
    }

    public function test_a_mistyped_milestone_address_is_not_found()
    {
        $this->actingAs($this->manager)
            ->delete("/projects/{$this->project->id}/milestones/not-a-number")
            ->assertNotFound();
    }

    public function test_new_projects_start_with_the_default_milestones()
    {
        $project = app(CreateProject::class)->handle('Crane', 'C1', null, $this->manager);

        $this->assertSame($this->defaultNames(), $project->milestones()->pluck('name')->all());
        $this->assertSame(['Required on site'], $project->milestones()->where('is_need_date', true)->pluck('name')->all());
    }

    public function test_the_page_lists_the_milestones_in_order_with_the_need_date()
    {
        $this->actingAs($this->manager)
            ->get(route('projects.milestones.index', $this->project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('projects/Milestones')
                ->has('milestones', 7)
                ->where('milestones.0.name', 'Specification ready')
                ->where('milestones.0.isNeedDate', false)
                ->where('milestones.5.name', 'Required on site')
                ->where('milestones.5.isNeedDate', true)
                ->where('maxMilestones', 30));
    }

    public function test_a_milestone_is_added_at_the_end()
    {
        $this->actingAs($this->manager)
            ->post(route('projects.milestones.store', $this->project), ['name' => '  Factory acceptance test  '])
            ->assertSessionHasNoErrors();

        $added = $this->project->milestones()->get()->last();
        $this->assertSame('Factory acceptance test', $added->name);
        $this->assertFalse($added->is_need_date);
    }

    public function test_a_milestone_is_added_after_the_last_one_even_after_removals_and_moves()
    {
        $this->milestone('Installed / commissioned')->delete();
        $this->move('Specification ready', 'down');

        $this->actingAs($this->manager)
            ->post(route('projects.milestones.store', $this->project), ['name' => 'Handover'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Request for quotation sent', 'Specification ready'], array_slice($this->names(), 0, 2));
        $this->assertSame('Handover', array_last($this->names()));
    }

    public function test_invalid_names_are_refused()
    {
        foreach (['', '   ', str_repeat('x', 101), 'required ON SITE'] as $name) {
            $this->actingAs($this->manager)
                ->post(route('projects.milestones.store', $this->project), ['name' => $name])
                ->assertSessionHasErrors('name');
        }

        $this->assertSame($this->defaultNames(), $this->names());
    }

    public function test_the_same_name_may_be_used_in_another_project()
    {
        ProjectMilestone::factory()->create(['name' => 'Handover']);

        $this->actingAs($this->manager)
            ->post(route('projects.milestones.store', $this->project), ['name' => 'Handover'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_project_has_at_most_thirty_milestones()
    {
        foreach (range(8, 30) as $number) {
            ProjectMilestone::factory()->for($this->project)->create(['name' => "Step {$number}", 'position' => $number]);
        }

        $this->actingAs($this->manager)
            ->post(route('projects.milestones.store', $this->project), ['name' => 'One too many'])
            ->assertSessionHasErrors(['name' => 'A project can have at most 30 milestones.']);

        $this->assertSame(30, $this->project->milestones()->count());
    }

    public function test_a_milestone_can_be_renamed_including_a_change_of_case()
    {
        $milestone = $this->milestone('Order placed');

        $this->actingAs($this->manager)
            ->put(route('projects.milestones.update', [$this->project, $milestone]), ['name' => 'ORDER PLACED'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ORDER PLACED', $milestone->refresh()->name);

        $this->actingAs($this->manager)
            ->put(route('projects.milestones.update', [$this->project, $milestone]), ['name' => 'offers received'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->manager)
            ->put(route('projects.milestones.update', [$this->project, $milestone]), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('ORDER PLACED', $milestone->refresh()->name);
    }

    public function test_milestones_move_up_and_down()
    {
        $this->move('Offers received', 'up');
        $this->assertSame(['Specification ready', 'Offers received', 'Request for quotation sent'], array_slice($this->names(), 0, 3));

        $this->move('Offers received', 'down');
        $this->assertSame($this->defaultNames(), $this->names());
    }

    public function test_moving_past_either_end_changes_nothing()
    {
        $this->move('Specification ready', 'up');
        $this->move('Installed / commissioned', 'down');

        $this->assertSame($this->defaultNames(), $this->names());
    }

    public function test_moving_closes_gaps_left_by_removed_milestones()
    {
        $this->milestone('Request for quotation sent')->delete();

        $this->move('Offers received', 'up');

        $this->assertSame([0, 1, 2, 3, 4, 5], $this->project->milestones()->pluck('position')->all());
        $this->assertSame(['Offers received', 'Specification ready'], array_slice($this->names(), 0, 2));
    }

    public function test_an_unknown_direction_is_refused()
    {
        $this->actingAs($this->manager)
            ->patch(route('projects.milestones.move', [$this->project, $this->milestone('Order placed')]), ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }

    public function test_making_another_milestone_the_need_date_moves_the_flag()
    {
        $this->actingAs($this->manager)
            ->put(route('projects.milestones.update', [$this->project, $this->milestone('Delivered from supplier')]), ['is_need_date' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Delivered from supplier'], $this->needDates());
    }

    public function test_the_need_date_cannot_simply_be_switched_off()
    {
        $this->actingAs($this->manager)
            ->put(route('projects.milestones.update', [$this->project, $this->milestone('Required on site')]), ['is_need_date' => false])
            ->assertSessionHasErrors('is_need_date');

        $this->assertSame(['Required on site'], $this->needDates());
    }

    public function test_the_need_date_cannot_be_removed()
    {
        $needDate = $this->milestone('Required on site');

        $this->actingAs($this->manager)
            ->delete(route('projects.milestones.destroy', [$this->project, $needDate]))
            ->assertSessionHasErrors(['milestone' => 'This milestone is the need date. Make another milestone the need date first.']);

        $this->assertModelExists($needDate);
    }

    public function test_removing_a_milestone_removes_every_packages_dates_for_it()
    {
        $ordered = $this->milestone('Order placed');
        $delivered = $this->milestone('Delivered from supplier');
        $package = Package::factory()->for($this->project)->create();
        PackageMilestone::factory()->for($package)->for($ordered)->create(['planned_date' => '2027-03-01', 'lead_days' => 10]);
        $kept = PackageMilestone::factory()->for($package)->for($delivered)->create(['planned_date' => '2027-04-01']);

        $this->actingAs($this->manager)
            ->delete(route('projects.milestones.destroy', [$this->project, $ordered]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($ordered);
        $this->assertDatabaseMissing('package_milestones', ['project_milestone_id' => $ordered->id]);
        $this->assertModelExists($kept);
    }

    public function test_the_page_counts_packages_with_dates_per_milestone_in_a_fixed_number_of_queries()
    {
        $ordered = $this->milestone('Order placed');
        $this->giveDates(Package::factory()->for($this->project)->create(), $ordered);

        $queriesForOne = $this->countQueries(fn () => $this->actingAs($this->manager->fresh())->get(route('projects.milestones.index', $this->project)));

        $delivered = $this->milestone('Delivered from supplier');
        foreach (Package::factory()->count(4)->for($this->project)->create() as $package) {
            $this->giveDates($package, $ordered);
            $this->giveDates($package, $delivered);
        }
        $this->giveDates(Package::factory()->create(), ProjectMilestone::factory()->create());

        $queriesForMany = $this->countQueries(fn () => $this->actingAs($this->manager->fresh())->get(route('projects.milestones.index', $this->project)));

        $this->assertSame($queriesForOne, $queriesForMany);

        $this->actingAs($this->manager)
            ->get(route('projects.milestones.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->where('milestones.0.packagesWithDatesCount', 0)
                ->where('milestones.3.packagesWithDatesCount', 5)
                ->where('milestones.4.packagesWithDatesCount', 4));
    }

    private function url(string $route): string
    {
        return match ($route) {
            'projects.milestones.index', 'projects.milestones.store' => route($route, $this->project),
            default => route($route, [$this->project, $this->milestone('Order placed')]),
        };
    }

    private function milestone(string $name): ProjectMilestone
    {
        return $this->project->milestones()->where('name', $name)->sole();
    }

    private function move(string $name, string $direction): void
    {
        $this->actingAs($this->manager)
            ->patch(route('projects.milestones.move', [$this->project, $this->milestone($name)]), ['direction' => $direction])
            ->assertSessionHasNoErrors();
    }

    private function giveDates(Package $package, ProjectMilestone $milestone): void
    {
        PackageMilestone::factory()->for($package)->for($milestone)->create(['forecast_date' => '2027-05-01']);
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return $this->project->milestones()->pluck('name')->all();
    }

    /**
     * @return list<string>
     */
    private function needDates(): array
    {
        return $this->project->milestones()->where('is_need_date', true)->pluck('name')->all();
    }

    /**
     * @return list<string>
     */
    private function defaultNames(): array
    {
        return [
            'Specification ready',
            'Request for quotation sent',
            'Offers received',
            'Order placed',
            'Delivered from supplier',
            'Required on site',
            'Installed / commissioned',
        ];
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
