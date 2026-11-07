<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectCategoryRequest;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProjectCategoryController extends Controller
{
    public function store(ProjectCategoryRequest $request, Project $project): RedirectResponse
    {
        $project->categories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category added.')]);

        return back();
    }

    public function update(ProjectCategoryRequest $request, Project $project, ProjectCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return back();
    }

    public function destroy(Project $project, ProjectCategory $category): RedirectResponse
    {
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category removed.')]);

        return back();
    }
}
