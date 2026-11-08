<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Packages\CreatePackage;
use App\Actions\Packages\NotifyNewPackageAssignees;
use App\Enums\PackageStatus;
use App\Enums\ProjectRole;
use App\Enums\PurchaseType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PackageListRisk;
use App\Http\Presenters\PackageListSchedule;
use App\Http\Presenters\PackageRiskOverview;
use App\Http\Presenters\PackageScheduleOverview;
use App\Http\Presenters\ProjectHeader;
use App\Http\Requests\Packages\StorePackageRequest;
use App\Http\Requests\Packages\UpdatePackageRequest;
use App\Jobs\CheckProjectRisks;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Policies\PackagePolicy;
use App\Risk\ProjectRisks;
use App\Scheduling\ProjectSchedules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    /**
     * The whole package tree at once: the page filters and folds it without asking the server again.
     * The tree is deferred: it is the slow part with thousands of packages, so the page opens without it
     * and asks for it in a second request right away.
     */
    public function index(Request $request, Project $project): Response
    {
        $user = $request->user();

        return Inertia::render('projects/packages/Index', [
            'project' => ProjectHeader::for($project, $user),
            'packages' => Inertia::defer(fn () => $this->packageTree($project, $user)),
            'categories' => $this->categories($project),
            'categoryRequired' => $project->number_includes_category,
            'statuses' => PackageStatus::options(),
            'purchaseTypes' => PurchaseType::options(),
            'can' => ['create' => $user->can('create', [Package::class, $project])],
        ]);
    }

    public function store(StorePackageRequest $request, Project $project, CreatePackage $createPackage): RedirectResponse
    {
        $parent = $request->filled('parent_id') ? $project->packages()->findOrFail($request->integer('parent_id')) : null;

        $package = $createPackage->handle(
            project: $project,
            title: $request->validated('title'),
            category: $this->chosenCategory($request, $project, $parent),
            parent: $parent,
        );
        // Its first check only stores its level, so later changes have something to compare with.
        CheckProjectRisks::dispatch($project->id, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package :number added.', ['number' => $package->number])]);

        return back();
    }

    public function show(Request $request, Project $project, Package $package): Response
    {
        $user = $request->user();
        $package->load(['category:id,code,name', 'owner:id,name', 'buyer:id,name']);

        return Inertia::render('projects/packages/Show', [
            'project' => ProjectHeader::for($project, $user),
            'package' => [
                'id' => $package->id,
                'parentId' => $package->parent_id,
                'number' => $package->number,
                'title' => $package->title,
                'category' => $package->category?->only(['id', 'code', 'name']),
                'status' => $package->status,
                'statusLabel' => $package->status->label(),
                'purchaseType' => $package->purchase_type?->label(),
                'ownerName' => $package->owner?->name,
                'buyerName' => $package->buyer?->name,
                'quantity' => $package->quantity,
                'unit' => $package->unit,
                'description' => $package->description,
                'notes' => $package->notes,
            ],
            'ancestors' => $this->ancestors($project, $package),
            'schedule' => PackageScheduleOverview::for($project, $package),
            'risk' => PackageRiskOverview::for($project, $package),
            'subPackages' => $this->subPackages($package),
            'categories' => $this->categories($project),
            'can' => [
                'update' => $user->can('update', $package),
                'create' => $user->can('create', [Package::class, $project]),
            ],
        ]);
    }

    public function edit(Request $request, Project $project, Package $package): Response
    {
        $canAssignPeople = $request->user()->can('create', [Package::class, $project]);
        $package->load(['owner:id,name', 'buyer:id,name']);

        return Inertia::render('projects/packages/Edit', [
            'project' => ProjectHeader::for($project, $request->user()),
            'package' => [
                'id' => $package->id,
                'number' => $package->number,
                'title' => $package->title,
                'categoryId' => $package->category_id,
                'status' => $package->status,
                'purchaseType' => $package->purchase_type,
                'ownerId' => $package->owner_id,
                'ownerName' => $package->owner?->name,
                'buyerId' => $package->buyer_id,
                'buyerName' => $package->buyer?->name,
                'quantity' => $package->quantity,
                'unit' => $package->unit,
                'description' => $package->description,
                'notes' => $package->notes,
            ],
            'categories' => $this->categories($project),
            'categoryRequired' => $project->number_includes_category && $package->parent_id === null,
            'statuses' => PackageStatus::options(),
            'purchaseTypes' => PurchaseType::options(),
            'canAssignPeople' => $canAssignPeople,
            // People are listed only for those who may choose them.
            'ownerOptions' => $canAssignPeople ? $this->peopleToChoose($project, ProjectRole::owningPackages(), $package->owner_id) : [],
            'buyerOptions' => $canAssignPeople ? $this->peopleToChoose($project, ProjectRole::buyingPackages(), $package->buyer_id) : [],
        ]);
    }

    public function update(UpdatePackageRequest $request, Project $project, Package $package, NotifyNewPackageAssignees $notifyNewAssignees): RedirectResponse
    {
        $package->fill($request->safe()->except(['owner_id', 'buyer_id']));

        // Left out of the validated data for package owners: they may update their package, but not hand it to someone else.
        if ($request->safe()->has('owner_id')) {
            $package->owner_id = $request->validated('owner_id');
        }

        if ($request->safe()->has('buyer_id')) {
            $package->buyer_id = $request->validated('buyer_id');
        }

        $package->save();
        $notifyNewAssignees->handle($package, $request->user());
        CheckProjectRisks::dispatch($project->id, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package saved.')]);

        return to_route('projects.packages.show', [$project, $package]);
    }

    /**
     * Every package of the project in tree order, read as plain rows because a project can have thousands.
     * Each row is an object, not an array, because Inertia inspects every value inside arrays one by one:
     * with 3,000 packages that alone took 0.1 seconds, and over a second with its development tools on.
     *
     * @return array<int, object>
     */
    private function packageTree(Project $project, User $user): array
    {
        /** @var PackagePolicy $policy */
        $policy = Gate::getPolicyFor(Package::class);

        // The risk rules' query, with the list's own columns added, so the rows are read once.
        $rows = ProjectRisks::packageRows($project)
            ->leftJoin('project_categories as categories', 'categories.id', '=', 'packages.category_id')
            ->addSelect([
                'packages.number', 'packages.title', 'packages.purchase_type', 'packages.category_id',
                DB::raw('array_length(packages.number_path, 1) - 1 as depth'),
                'categories.code as category_code', 'owners.name as owner_name', 'buyers.name as buyer_name',
            ])
            ->get();
        $schedules = ProjectSchedules::for($project, $rows);
        $columns = PackageListSchedule::for($schedules);
        $risks = ProjectRisks::fromRows($project, $rows, $schedules);

        return $rows
            ->map(fn (object $row) => (object) [
                'id' => $row->id,
                'parentId' => $row->parent_id,
                'depth' => $row->depth,
                'number' => $row->number,
                'title' => $row->title,
                'categoryId' => $row->category_id,
                'categoryCode' => $row->category_code,
                'status' => $row->status,
                'purchaseType' => $row->purchase_type,
                'ownerName' => $row->owner_name,
                'buyerName' => $row->buyer_name,
                // Asked directly instead of through Gate: the same rule, several times faster for thousands of rows.
                'canEdit' => $policy->updateOwnedBy($user, $project, $row->owner_id),
                'risk' => PackageListRisk::for($risks[$row->id], $row->risk_flag_reason, $schedules),
                ...$columns[$row->id],
            ])
            ->all();
    }

    /**
     * A sub-package gets its parent's category unless another one, or none, is chosen.
     */
    private function chosenCategory(StorePackageRequest $request, Project $project, ?Package $parent): ?ProjectCategory
    {
        if ($parent !== null && ! $request->exists('category_id')) {
            return $parent->category;
        }

        return $request->filled('category_id') ? $project->categories()->findOrFail($request->integer('category_id')) : null;
    }

    /**
     * @return Collection<int, array{id: int, code: string, name: string}>
     */
    private function categories(Project $project): Collection
    {
        return $project->categories()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (ProjectCategory $category) => ['id' => $category->id, 'code' => $category->code, 'name' => $category->name]);
    }

    /**
     * Every package above this one, from the top-level package down, found with one query.
     *
     * @return Collection<int, array{id: int, number: string, title: string}>
     */
    private function ancestors(Project $project, Package $package): Collection
    {
        $paths = array_map(fn (array $path) => '{'.implode(',', $path).'}', $package->ancestorNumberPaths());

        if ($paths === []) {
            return collect();
        }

        $placeholders = implode(', ', array_fill(0, count($paths), '?::integer[]'));

        return $project->packages()
            ->whereRaw("number_path in ({$placeholders})", $paths)
            ->orderBy('number_path')
            ->get(['id', 'number', 'title'])
            ->map(fn (Package $ancestor) => ['id' => $ancestor->id, 'number' => $ancestor->number, 'title' => $ancestor->title]);
    }

    /**
     * @return Collection<int, array{id: int, number: string, title: string, categoryCode: string|null, status: PackageStatus}>
     */
    private function subPackages(Package $package): Collection
    {
        return $package->children()
            ->with('category:id,code')
            ->orderBy('number_path')
            ->get(['id', 'number', 'title', 'category_id', 'status'])
            ->map(fn (Package $child) => [
                'id' => $child->id,
                'number' => $child->number,
                'title' => $child->title,
                'categoryCode' => $child->category?->code,
                'status' => $child->status,
            ]);
    }

    /**
     * Active project members with one of the roles, plus the person chosen now, so the form still shows them
     * after they are deactivated.
     *
     * @param  list<ProjectRole>  $roles
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    private function peopleToChoose(Project $project, array $roles, ?int $currentId): Collection
    {
        return User::query()
            ->join('project_members', 'project_members.user_id', '=', 'users.id')
            ->where('project_members.project_id', $project->id)
            ->whereIn('project_members.role', array_map(fn (ProjectRole $role) => $role->value, $roles))
            ->where(fn ($people) => $people->whereNull('users.deactivated_at')->orWhere('users.id', $currentId))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }
}
