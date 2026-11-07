<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\MoveProjectMilestone;
use App\Enums\MoveDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\MoveProjectMilestoneRequest;
use App\Jobs\CheckProjectRisks;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\RedirectResponse;

class MoveProjectMilestoneController extends Controller
{
    public function __invoke(MoveProjectMilestoneRequest $request, Project $project, ProjectMilestone $milestone, MoveProjectMilestone $moveMilestone): RedirectResponse
    {
        $moveMilestone->handle($milestone, $request->enum('direction', MoveDirection::class));
        // Lead times count from the previous milestone, so moving one changes expected dates.
        CheckProjectRisks::dispatch($project->id, $request->user()->id);

        return back();
    }
}
