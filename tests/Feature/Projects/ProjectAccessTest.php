<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pages and actions every project member may use.
     *
     * @return array<string, array{string, string}>
     */
    public static function memberRoutes(): array
    {
        return [
            'overview' => ['get', 'projects.show'],
            'members' => ['get', 'projects.members.index'],
        ];
    }

    /**
     * Pages and actions only project managers and admins may use.
     *
     * @return array<string, array{string, string}>
     */
    public static function managerRoutes(): array
    {
        return [
            'settings page' => ['get', 'projects.edit'],
            'save settings' => ['put', 'projects.update'],
            'numbering page' => ['get', 'projects.numbering.edit'],
            'save numbering' => ['put', 'projects.numbering.update'],
            'add category' => ['post', 'projects.categories.store'],
            'edit category' => ['put', 'projects.categories.update'],
            'remove category' => ['delete', 'projects.categories.destroy'],
            'add member' => ['post', 'projects.members.store'],
            'change role' => ['patch', 'projects.members.update'],
            'remove member' => ['delete', 'projects.members.destroy'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function allProjectRoutes(): array
    {
        return [...self::memberRoutes(), ...self::managerRoutes()];
    }

    public function test_guests_are_sent_to_login()
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->post(route('projects.store'))->assertRedirect(route('login'));
    }

    #[DataProvider('allProjectRoutes')]
    public function test_people_outside_the_project_get_nothing(string $method, string $route)
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->call($method, $this->url($route, $project))
            ->assertForbidden();
    }

    #[DataProvider('memberRoutes')]
    public function test_every_member_can_open_the_project(string $method, string $route)
    {
        $viewer = User::factory()->create();
        $project = Project::factory()->withMember($viewer, ProjectRole::Viewer)->create();

        $this->actingAs($viewer)->call($method, $this->url($route, $project))->assertOk();
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
    public function test_only_project_managers_and_admins_can_change_the_project(ProjectRole $role)
    {
        $user = User::factory()->create();
        $project = Project::factory()->withMember($user, $role)->create();

        foreach (self::managerRoutes() as [$method, $route]) {
            $this->actingAs($user)
                ->call($method, $this->url($route, $project), ['name' => 'Changed', 'code' => 'X1', 'role' => 'viewer'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('projects', ['name' => 'Changed']);
    }

    public function test_project_managers_and_admins_can_open_the_settings_pages()
    {
        $manager = User::factory()->create();
        $project = Project::factory()->withMember($manager, ProjectRole::ProjectManager)->create();

        foreach ([$manager, User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('projects.edit', $project))->assertOk();
            $this->actingAs($user)->get(route('projects.numbering.edit', $project))->assertOk();
        }
    }

    public function test_the_list_shows_only_the_projects_a_person_belongs_to()
    {
        $user = User::factory()->create();
        Project::factory()->withMember($user, ProjectRole::Buyer)->create(['name' => 'Mine', 'code' => 'P1']);
        Project::factory()->create(['name' => 'Someone else’s']);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page
                ->component('projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Mine')
                ->where('projects.data.0.myRole', 'Buyer')
                ->where('projects.data.0.membersCount', 1)
                ->where('can.create', false)
                ->missing('managerOptions'));
    }

    public function test_admins_see_every_project()
    {
        Project::factory()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page
                ->has('projects.data', 3)
                ->where('projects.data.0.myRole', null)
                ->where('can.create', true));
    }

    public function test_the_list_can_be_searched_by_name_or_code()
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'Harbour crane', 'code' => 'H200']);
        Project::factory()->create(['name' => 'Paper mill', 'code' => 'P300']);

        $this->actingAs($admin)
            ->get(route('projects.index', ['search' => 'crane']))
            ->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('projects.data.0.code', 'H200'));

        $this->actingAs($admin)
            ->get(route('projects.index', ['search' => 'p300']))
            ->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('projects.data.0.code', 'P300'));
    }

    public function test_the_list_uses_the_same_number_of_queries_for_any_number_of_projects()
    {
        $user = User::factory()->create();
        Project::factory()->withMember($user)->create();

        $queriesForOne = $this->countQueries(fn () => $this->actingAs($user)->get(route('projects.index')));

        Project::factory()->count(10)->withMember($user)->create();

        $queriesForEleven = $this->countQueries(fn () => $this->actingAs($user)->get(route('projects.index')));

        $this->assertSame($queriesForOne, $queriesForEleven);
    }

    public function test_a_mistyped_project_address_is_not_found()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/projects/not-a-number')
            ->assertNotFound();
    }

    private function url(string $route, Project $project): string
    {
        return match ($route) {
            'projects.categories.update', 'projects.categories.destroy' => route($route, [$project, ProjectCategory::factory()->for($project)->create()]),
            'projects.members.update', 'projects.members.destroy' => route($route, [$project, ProjectMember::factory()->for($project)->create()]),
            default => route($route, $project),
        };
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
