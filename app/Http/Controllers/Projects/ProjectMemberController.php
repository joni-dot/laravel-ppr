<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\ChangeProjectMemberRole;
use App\Actions\Projects\RemoveProjectMember;
use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectHeader;
use App\Http\Requests\Projects\StoreProjectMemberRequest;
use App\Http\Requests\Projects\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $canManage = $request->user()->can('manageMembers', $project);

        $members = $project->members()
            ->with('user:id,name,email,password,deactivated_at')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->orderBy('users.name')
            ->get(['project_members.id', 'project_members.user_id', 'project_members.role'])
            ->map(fn (ProjectMember $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'status' => $member->user->status(),
                'role' => $member->role,
            ]);

        return Inertia::render('projects/Members', [
            'project' => ProjectHeader::for($project, $request->user()),
            'members' => $members,
            'roles' => ProjectRole::options(),
            // Only loaded when the "Add member" dialog opens, and only for people who may add members.
            ...($canManage ? ['userOptions' => Inertia::optional(fn () => $this->peopleNotInProject($project))] : []),
        ]);
    }

    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $member = $project->members()->make(['role' => $request->enum('role', ProjectRole::class)]);
        $member->user_id = $request->integer('user_id');
        $member->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return back();
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, ProjectMember $member, ChangeProjectMemberRole $changeRole): RedirectResponse
    {
        $changeRole->handle($member, $request->enum('role', ProjectRole::class));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role changed.')]);

        return back();
    }

    public function destroy(Project $project, ProjectMember $member, RemoveProjectMember $removeMember): RedirectResponse
    {
        $removeMember->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    private function peopleNotInProject(Project $project): Collection
    {
        return User::query()
            ->notDeactivated()
            ->whereDoesntHave('projectMemberships', fn ($membership) => $membership->where('project_id', $project->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
