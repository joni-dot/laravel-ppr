<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectCategoryRequest;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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
        if ($category->packages()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('Packages use this category, so it can\'t be removed. Give them another category first.'),
            ]);
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category removed.')]);

        return back();
    }
}
