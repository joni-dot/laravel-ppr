<?php

use App\Http\Controllers\Projects\ProjectCategoryController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\ProjectNumberingController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

// Only numbers reach the database, so a mistyped address is a "not found" instead of a database error.
Route::middleware('auth')
    ->prefix('projects')
    ->name('projects.')
    ->where(['project' => '[0-9]+', 'category' => '[0-9]+', 'member' => '[0-9]+'])
    ->group(function () {
        Route::get('/', [ProjectController::class, 'index'])
            ->can('viewAny', Project::class)
            ->name('index');

        Route::post('/', [ProjectController::class, 'store'])
            ->can('create', Project::class)
            ->name('store');

        Route::get('{project}', [ProjectController::class, 'show'])
            ->can('view', 'project')
            ->name('show');

        Route::get('{project}/edit', [ProjectController::class, 'edit'])
            ->can('update', 'project')
            ->name('edit');

        Route::put('{project}', [ProjectController::class, 'update'])
            ->can('update', 'project')
            ->name('update');

        Route::get('{project}/numbering', [ProjectNumberingController::class, 'edit'])
            ->can('update', 'project')
            ->name('numbering.edit');

        Route::put('{project}/numbering', [ProjectNumberingController::class, 'update'])
            ->can('update', 'project')
            ->name('numbering.update');

        // Scoped, so a category or member of another project can never be reached through this project's address.
        Route::scopeBindings()->group(function () {
            Route::post('{project}/categories', [ProjectCategoryController::class, 'store'])
                ->can('update', 'project')
                ->name('categories.store');

            Route::put('{project}/categories/{category}', [ProjectCategoryController::class, 'update'])
                ->can('update', 'project')
                ->name('categories.update');

            Route::delete('{project}/categories/{category}', [ProjectCategoryController::class, 'destroy'])
                ->can('update', 'project')
                ->name('categories.destroy');

            Route::get('{project}/members', [ProjectMemberController::class, 'index'])
                ->can('view', 'project')
                ->name('members.index');

            Route::post('{project}/members', [ProjectMemberController::class, 'store'])
                ->can('manageMembers', 'project')
                ->name('members.store');

            Route::patch('{project}/members/{member}', [ProjectMemberController::class, 'update'])
                ->can('manageMembers', 'project')
                ->name('members.update');

            Route::delete('{project}/members/{member}', [ProjectMemberController::class, 'destroy'])
                ->can('manageMembers', 'project')
                ->name('members.destroy');
        });
    });
