<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Packages\UpdatePackageMilestones;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectHeader;
use App\Http\Requests\Packages\UpdatePackageScheduleRequest;
use App\Jobs\CheckProjectRisks;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Scheduling\Days;
use App\Scheduling\MilestoneEntries;
use App\Scheduling\MilestoneEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A package's planned, forecast and actual milestone dates and its lead times.
 */
class PackageScheduleController extends Controller
{
    public function edit(Request $request, Project $project, Package $package): Response
    {
        $entries = MilestoneEntries::forProject($project->id, [$package->id])[$package->id] ?? [];
        $empty = new MilestoneEntry;

        return Inertia::render('projects/packages/Schedule', [
            'project' => ProjectHeader::for($project, $request->user()),
            'package' => ['id' => $package->id, 'number' => $package->number, 'title' => $package->title],
            'milestones' => $project->milestones()
                ->get(['id', 'name', 'is_need_date'])
                ->map(fn (ProjectMilestone $milestone) => $this->editableMilestone($milestone, $entries[$milestone->id] ?? $empty)),
            'canChangePlannedDates' => $request->user()->can('changePlannedDates', [Package::class, $project]),
            // The same limit the server checks, so the form refuses the same future actual dates.
            'today' => Days::latestToday(),
        ]);
    }

    public function update(UpdatePackageScheduleRequest $request, Project $project, Package $package, UpdatePackageMilestones $updateMilestones): RedirectResponse
    {
        $updateMilestones->handle(
            $package,
            $request->milestoneRows(),
            mayChangePlannedDates: $request->user()->can('changePlannedDates', [Package::class, $project]),
        );
        CheckProjectRisks::dispatch($project->id, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dates saved.')]);

        return to_route('projects.packages.show', [$project, $package]);
    }

    /**
     * @return array{id: int, name: string, isNeedDate: bool, plannedDate: string|null, forecastDate: string|null, actualDate: string|null, leadDays: int|null}
     */
    private function editableMilestone(ProjectMilestone $milestone, MilestoneEntry $entry): array
    {
        return [
            'id' => $milestone->id,
            'name' => $milestone->name,
            'isNeedDate' => $milestone->is_need_date,
            'plannedDate' => $entry->planned,
            'forecastDate' => $entry->forecast,
            'actualDate' => $entry->actual,
            'leadDays' => $entry->leadDays,
        ];
    }
}
