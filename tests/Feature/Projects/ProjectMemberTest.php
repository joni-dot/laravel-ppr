<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['name' => 'Mia Manager']);
        $this->project = Project::factory()->withMember($this->manager, ProjectRole::ProjectManager)->create();
    }

    public function test_members_see_the_team_without_secrets()
    {
        $viewer = User::factory()->create(['name' => 'Vic Viewer']);
        ProjectMember::factory()->for($this->project)->for($viewer)->create(['role' => ProjectRole::Viewer]);

        $this->actingAs($viewer)
            ->get(route('projects.members.index', $this->project))
            ->assertOk()
            ->assertDontSee('$2y$', escape: false)
            ->assertInertia(fn ($page) => $page
                ->component('projects/Members')
                ->has('members', 2)
                ->where('members.0.name', 'Mia Manager')
                ->where('members.0.role', 'project_manager')
                ->where('members.1.name', 'Vic Viewer')
                ->where('members.1.status', 'active')
                ->where('project.can.manageMembers', false)
                ->missing('members.0.password')
                ->reload(fn ($reload) => $reload->missing('userOptions'), only: 'userOptions'));
    }

    public function test_the_people_offered_are_active_and_not_yet_members()
    {
        User::factory()->create(['name' => 'Ann Available']);
        User::factory()->invited()->create(['name' => 'Ivy Invited']);
        User::factory()->deactivated()->create(['name' => 'Dan Deactivated']);

        $this->actingAs($this->manager)
            ->get(route('projects.members.index', $this->project))
            ->assertInertia(fn ($page) => $page
                ->missing('userOptions')
                ->reloadOnly('userOptions', fn ($reload) => $reload
                    ->has('userOptions', 2)
                    ->where('userOptions.0.name', 'Ann Available')
                    ->where('userOptions.1.name', 'Ivy Invited')));
    }

    public function test_a_project_manager_can_add_a_member()
    {
        $buyer = User::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), ['user_id' => $buyer->id, 'role' => 'buyer'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectRole::Buyer, $buyer->roleIn($this->project));
    }

    public function test_a_person_can_be_added_only_once()
    {
        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), ['user_id' => $this->manager->id, 'role' => 'viewer'])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, $this->project->members()->count());
    }

    public function test_deactivated_people_cannot_be_added()
    {
        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), ['user_id' => User::factory()->deactivated()->create()->id, 'role' => 'viewer'])
            ->assertSessionHasErrors('user_id');
    }

    public function test_a_project_manager_can_change_a_role()
    {
        $member = ProjectMember::factory()->for($this->project)->create(['role' => ProjectRole::Viewer]);

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $member]), ['role' => 'package_owner'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectRole::PackageOwner, $member->refresh()->role);
    }

    public function test_a_project_manager_can_remove_a_member_without_touching_their_account()
    {
        $member = ProjectMember::factory()->for($this->project)->create();

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $member]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($member);
        $this->assertModelExists($member->user);
    }

    public function test_the_only_project_manager_cannot_be_demoted_or_removed()
    {
        $membership = $this->project->members()->sole();

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $membership]), ['role' => 'viewer'])
            ->assertSessionHasErrors('role');

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $membership]))
            ->assertSessionHasErrors('role');

        $this->assertSame(ProjectRole::ProjectManager, $membership->refresh()->role);
    }

    public function test_a_project_manager_can_step_down_once_someone_else_manages_the_project()
    {
        ProjectMember::factory()->for($this->project)->create(['role' => ProjectRole::ProjectManager]);
        $membership = $this->project->members()->where('user_id', $this->manager->id)->sole();

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $membership]), ['role' => 'buyer'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectRole::Buyer, $membership->refresh()->role);
    }

    public function test_members_of_another_project_cannot_be_reached_through_this_one()
    {
        $otherMember = ProjectMember::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $otherMember]))
            ->assertNotFound();

        $this->assertModelExists($otherMember);
    }
}
