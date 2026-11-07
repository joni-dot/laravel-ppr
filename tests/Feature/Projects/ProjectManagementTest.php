<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\SettingKey;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_create_projects()
    {
        $manager = User::factory()->create();
        Project::factory()->withMember($manager, ProjectRole::ProjectManager)->create();

        $this->actingAs($manager)
            ->post(route('projects.store'), ['name' => 'New', 'code' => 'N1', 'manager_id' => $manager->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('projects', ['code' => 'N1']);
    }

    public function test_an_admin_creates_a_project_with_its_project_manager_and_the_company_risk_warnings()
    {
        Setting::put(SettingKey::RiskAmberDays, '21');
        Setting::put(SettingKey::RiskRedDays, '3');
        $manager = User::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('projects.store'), ['name' => 'Harbour crane', 'code' => 'H200', 'description' => 'New crane', 'manager_id' => $manager->id]);

        $project = Project::query()->where('code', 'H200')->sole();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame(21, $project->risk_amber_days);
        $this->assertSame(3, $project->risk_red_days);
        $this->assertSame(ProjectRole::ProjectManager, $manager->roleIn($project));
    }

    public function test_project_codes_are_unique_whatever_their_case()
    {
        Project::factory()->create(['code' => 'P100']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('projects.store'), ['name' => 'Copy', 'code' => 'p100', 'manager_id' => User::factory()->create()->id])
            ->assertSessionHasErrors('code');
    }

    public function test_project_codes_contain_only_letters_and_digits()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('projects.store'), ['name' => 'Bad', 'code' => 'P-100', 'manager_id' => User::factory()->create()->id])
            ->assertSessionHasErrors('code');
    }

    public function test_the_project_manager_must_be_an_active_user()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('projects.store'), ['name' => 'New', 'code' => 'N1', 'manager_id' => User::factory()->deactivated()->create()->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertDatabaseEmpty('projects');
    }

    public function test_the_list_of_possible_managers_is_loaded_only_when_asked_for()
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        User::factory()->create(['name' => 'Bea Buyer']);
        User::factory()->deactivated()->create(['name' => 'Dan Deactivated']);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page
                ->missing('managerOptions')
                ->reloadOnly('managerOptions', fn ($reload) => $reload
                    ->has('managerOptions', 2)
                    ->where('managerOptions.0.name', 'Ada Admin')
                    ->where('managerOptions.1.name', 'Bea Buyer')));
    }

    public function test_non_admins_can_never_load_the_list_of_people()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(fn ($page) => $page
                ->reload(fn ($reload) => $reload->missing('managerOptions'), only: 'managerOptions'));
    }

    public function test_the_overview_shows_the_team_risk_warnings_and_example_numbers()
    {
        $viewer = User::factory()->create();
        $project = Project::factory()
            ->withMember($viewer, ProjectRole::Viewer)
            ->withMember(User::factory()->create(), ProjectRole::ProjectManager)
            ->create(['code' => 'P100', 'description' => 'About the project']);
        ProjectCategory::factory()->for($project)->create(['code' => 'MEC']);
        ProjectCategory::factory()->for($project)->create(['code' => 'ELE']);

        $this->actingAs($viewer)
            ->get(route('projects.show', $project))
            ->assertInertia(fn ($page) => $page
                ->component('projects/Show')
                ->where('project.name', $project->name)
                ->where('project.can.update', false)
                ->where('description', 'About the project')
                ->where('team.0', ['role' => 'Project manager', 'count' => 1])
                ->where('team.3', ['role' => 'Viewer', 'count' => 1])
                ->where('risk', ['amberDays' => 14, 'redDays' => 0])
                ->where('exampleNumbers', ['package' => 'P100-ELE-0001', 'subPackage' => 'P100-ELE-0001.01']));
    }

    public function test_a_project_manager_can_change_the_details_and_risk_warnings()
    {
        $manager = User::factory()->create();
        $project = Project::factory()->withMember($manager, ProjectRole::ProjectManager)->create();

        $this->actingAs($manager)
            ->put(route('projects.update', $project), [
                'name' => 'Renamed',
                'code' => 'R1',
                'description' => null,
                'risk_amber_days' => 30,
                'risk_red_days' => 5,
            ])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('Renamed', $project->name);
        $this->assertSame('R1', $project->code);
        $this->assertSame(30, $project->risk_amber_days);
        $this->assertSame(5, $project->risk_red_days);
    }

    public function test_a_project_keeps_its_own_code_when_saved_unchanged()
    {
        $project = Project::factory()->create(['code' => 'P100']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('projects.update', $project), ['name' => 'Same', 'code' => 'p100', 'risk_amber_days' => 14, 'risk_red_days' => 0])
            ->assertSessionHasNoErrors();
    }

    public function test_red_must_start_closer_to_the_deadline_than_amber()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('projects.update', $project), ['name' => 'X', 'code' => 'X1', 'risk_amber_days' => 5, 'risk_red_days' => 5])
            ->assertSessionHasErrors('risk_amber_days');
    }
}
