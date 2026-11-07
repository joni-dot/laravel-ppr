<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\AddProjectMilestone;
use App\Actions\Projects\MakeMilestoneTheNeedDate;
use App\Actions\Projects\RemoveProjectMilestone;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectHeader;
use App\Http\Requests\Projects\StoreProjectMilestoneRequest;
use App\Http\Requests\Projects\UpdateProjectMilestoneRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectMilestoneController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        // A package has a row for a milestone only once something is filled in, so counting rows
        // counts the packages that would lose dates or a lead time if the milestone were removed.
        $milestones = $project->milestones()
            ->select(['id', 'name', 'is_need_date'])
            ->withCount('packageMilestones')
            ->get()
            ->map(fn (ProjectMilestone $milestone) => [
                'id' => $milestone->id,
                'name' => $milestone->name,
                'isNeedDate' => $milestone->is_need_date,
                'packagesWithDatesCount' => $milestone->package_milestones_count,
            ]);

        return Inertia::render('projects/Milestones', [
            'project' => ProjectHeader::for($project, $request->user()),
            'milestones' => $milestones,
            'maxMilestones' => AddProjectMilestone::MAX_PER_PROJECT,
        ]);
    }

    public function store(StoreProjectMilestoneRequest $request, Project $project, AddProjectMilestone $addMilestone): RedirectResponse
    {
        $addMilestone->handle($project, $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Milestone added.')]);

        return back();
    }

    public function update(UpdateProjectMilestoneRequest $request, Project $project, ProjectMilestone $milestone, MakeMilestoneTheNeedDate $makeNeedDate): RedirectResponse
    {
        $becomesNeedDate = $request->boolean('is_need_date');

        DB::transaction(function () use ($request, $milestone, $makeNeedDate, $becomesNeedDate) {
            if ($request->has('name')) {
                $milestone->name = $request->validated('name');
                $milestone->save();
            }

            if ($becomesNeedDate) {
                $makeNeedDate->handle($milestone);
            }
        });

        $message = $becomesNeedDate
            ? __(':name is now the need date.', ['name' => $milestone->name])
            : __('Milestone saved.');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function destroy(Project $project, ProjectMilestone $milestone, RemoveProjectMilestone $removeMilestone): RedirectResponse
    {
        $removeMilestone->handle($milestone);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Milestone removed.')]);

        return back();
    }
}
