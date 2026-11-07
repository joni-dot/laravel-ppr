<?php

namespace App\Http\Controllers\Projects;

use App\Enums\NumberSeparator;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ProjectHeader;
use App\Http\Requests\Projects\UpdateProjectNumberingRequest;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Numbering\PackageNumberFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectNumberingController extends Controller
{
    public function edit(Request $request, Project $project): Response
    {
        return Inertia::render('projects/Numbering', [
            'project' => ProjectHeader::for($project, $request->user()),
            'numbering' => [
                'prefix' => $project->number_prefix,
                'includesProjectCode' => $project->number_includes_project_code,
                'includesCategory' => $project->number_includes_category,
                'separator' => $project->number_separator,
                'padding' => $project->number_padding,
                'start' => $project->number_start,
                'subSeparator' => $project->sub_number_separator,
                'subPadding' => $project->sub_number_padding,
            ],
            // Ordered by the database, so the preview picks the same first category as the server.
            'categories' => $project->categories()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (ProjectCategory $category) => $category->only(['id', 'code', 'name'])),
            'exampleCategoryCode' => PackageNumberFormat::EXAMPLE_CATEGORY_CODE,
            // The preview shows the real next number, which skips numbers already given.
            'lastPackageNumber' => $project->last_package_number,
            'separators' => NumberSeparator::options(NumberSeparator::cases()),
            'subSeparators' => NumberSeparator::options(NumberSeparator::forSubNumbers()),
        ]);
    }

    public function update(UpdateProjectNumberingRequest $request, Project $project): RedirectResponse
    {
        $project->number_prefix = $request->validated('number_prefix');
        $project->number_includes_project_code = $request->boolean('number_includes_project_code');
        $project->number_includes_category = $request->boolean('number_includes_category');
        $project->number_separator = $request->enum('number_separator', NumberSeparator::class);
        $project->number_padding = $request->integer('number_padding');
        $project->number_start = $request->integer('number_start');
        $project->sub_number_separator = $request->enum('sub_number_separator', NumberSeparator::class);
        $project->sub_number_padding = $request->integer('sub_number_padding');
        $project->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Numbering saved.')]);

        return back();
    }
}
