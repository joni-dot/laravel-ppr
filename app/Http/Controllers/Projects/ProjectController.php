<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\CreateProject;
use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectHeader;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Numbering\PackageNumberFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    private const int PROJECTS_PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->string('search')->trim()->limit(100, '')->toString();

        $projects = Project::query()
            ->visibleTo($user)
            ->select(['id', 'name', 'code'])
            ->addSelect(['my_role' => ProjectMember::query()
                ->select('role')
                ->whereColumn('project_id', 'projects.id')
                ->where('user_id', $user->id)])
            ->withCount('members')
            ->when($search !== '', fn ($query) => $query->whereAny(['name', 'code'], 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->paginate(self::PROJECTS_PER_PAGE)
            ->withQueryString()
            ->through(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'membersCount' => $project->members_count,
                'myRole' => ProjectRole::tryFrom((string) $project->getAttribute('my_role'))?->label(),
            ]);

        $canCreate = $user->can('create', Project::class);

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'filters' => ['search' => $search],
            'can' => ['create' => $canCreate],
            // Only loaded when the "New project" dialog opens, and only for people who may create projects.
            ...($canCreate ? ['managerOptions' => Inertia::optional(fn () => $this->managerOptions())] : []),
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $project = $createProject->handle(
            name: $request->validated('name'),
            code: $request->validated('code'),
            description: $request->validated('description'),
            manager: User::findOrFail($request->integer('manager_id')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        $membersByRole = $project->members()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return Inertia::render('projects/Show', [
            'project' => ProjectHeader::for($project, $request->user()),
            'description' => $project->description,
            'team' => array_map(fn (ProjectRole $role) => [
                'role' => $role->label(),
                'count' => (int) ($membersByRole[$role->value] ?? 0),
            ], ProjectRole::cases()),
            'risk' => [
                'amberDays' => $project->risk_amber_days,
                'redDays' => $project->risk_red_days,
            ],
            'exampleNumbers' => $this->exampleNumbers($project),
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        return Inertia::render('projects/Edit', [
            'project' => ProjectHeader::for($project, $request->user()),
            'details' => [
                'name' => $project->name,
                'code' => $project->code,
                'description' => $project->description,
                'riskAmberDays' => $project->risk_amber_days,
                'riskRedDays' => $project->risk_red_days,
            ],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->fill($request->safe()->only(['name', 'code', 'description']));
        $project->risk_amber_days = $request->integer('risk_amber_days');
        $project->risk_red_days = $request->integer('risk_red_days');
        $project->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return back();
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    private function managerOptions(): Collection
    {
        return User::query()
            ->notDeactivated()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }

    /**
     * What the first package and its first sub-package will be numbered, using the first category's code.
     *
     * @return array{package: string, subPackage: string}
     */
    private function exampleNumbers(Project $project): array
    {
        $categoryCode = $project->categories()->orderBy('code')->value('code') ?? PackageNumberFormat::EXAMPLE_CATEGORY_CODE;
        $format = $project->numberFormat();

        return [
            'package' => $format->format($project->number_start, $categoryCode),
            'subPackage' => $format->format($project->number_start, $categoryCode, 1),
        ];
    }
}
