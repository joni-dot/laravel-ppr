<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Packages\ClearPackageRiskFlag;
use App\Actions\Packages\FlagPackageRisk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Packages\FlagPackageRiskRequest;
use App\Jobs\CheckProjectRisks;
use App\Models\Package;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Marking a package as high risk by hand, with a reason. Anyone who may edit the package may do it.
 */
class PackageRiskFlagController extends Controller
{
    public function store(FlagPackageRiskRequest $request, Project $project, Package $package, FlagPackageRisk $flagPackageRisk): RedirectResponse
    {
        $flagPackageRisk->handle($package, $request->user(), $request->validated('reason'));
        CheckProjectRisks::dispatch($project->id, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package flagged as high risk.')]);

        return to_route('projects.packages.show', [$project, $package]);
    }

    public function destroy(Request $request, Project $project, Package $package, ClearPackageRiskFlag $clearPackageRiskFlag): RedirectResponse
    {
        $clearPackageRiskFlag->handle($package);
        CheckProjectRisks::dispatch($project->id, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Risk flag cleared.')]);

        return to_route('projects.packages.show', [$project, $package]);
    }
}
