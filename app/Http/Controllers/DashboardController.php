<?php

namespace App\Http\Controllers;

use App\Dashboard\ProjectAttention;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A person's own view: what they must act on next across their projects, and one row of numbers per
 * project. Only projects the person is a member of count, admins included: an admin sees every project
 * elsewhere, but their dashboard is about their own work.
 */
class DashboardController extends Controller
{
    // A longer list wouldn't be read; the page says how many more there are.
    public const int ACTIONS_SHOWN = 50;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $projects = $this->memberProjects($user);

        return Inertia::render('Dashboard', [
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'role' => ProjectRole::from($project->getAttribute('my_role'))->label(),
            ])->all(),
            'notificationDays' => $user->notification_days,
            'can' => ['seeAllProjects' => $user->isAdmin()],
            // Deferred: it reads every package of every project, so the page opens with the names first.
            'attention' => Inertia::defer(fn () => $this->attention($projects, $user)),
        ]);
    }

    /**
     * @return Collection<int, Project> with my_role, the person's role in each
     */
    private function memberProjects(User $user): Collection
    {
        return Project::query()
            ->join('project_members', 'project_members.project_id', '=', 'projects.id')
            ->where('project_members.user_id', $user->id)
            ->orderBy('projects.name')
            ->orderBy('projects.id')
            ->get(['projects.*', 'project_members.role as my_role']);
    }

    /**
     * Each project's numbers and the person's actions, worked out once per project.
     *
     * @param  Collection<int, Project>  $projects
     * @return array{tiles: list<array{projectId: int, tiles: array<string, int>}>, actions: list<array<string, mixed>>, actionsTotal: int}
     */
    private function attention(Collection $projects, User $user): array
    {
        $tiles = [];
        $actions = [];

        foreach ($projects as $project) {
            $attention = ProjectAttention::for($project);
            $tiles[] = ['projectId' => $project->id, 'tiles' => $attention->tiles()];

            foreach ($attention->actionsFor($user->id, $user->notification_days) as $action) {
                $actions[] = ['projectId' => $project->id, 'projectCode' => $project->code, ...$action];
            }
        }

        // Earliest first, so the most overdue lead.
        usort($actions, fn (array $a, array $b) => [$a['date'], $a['projectCode'], $a['number']] <=> [$b['date'], $b['projectCode'], $b['number']]);

        return [
            'tiles' => $tiles,
            'actions' => array_slice($actions, 0, self::ACTIONS_SHOWN),
            'actionsTotal' => count($actions),
        ];
    }
}
