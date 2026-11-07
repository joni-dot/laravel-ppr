<?php

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Support\Facades\DB;

/**
 * Adds a package with just a title and a category, so many can be added quickly.
 * The other details are filled in later.
 */
class CreatePackage
{
    public function __construct(private readonly AssignPackageNumber $assignPackageNumber) {}

    public function handle(Project $project, string $title, ?ProjectCategory $category, ?Package $parent = null): Package
    {
        return DB::transaction(function () use ($project, $title, $category, $parent) {
            $package = new Package(['title' => $title]);
            $package->project_id = $project->id;
            $package->category()->associate($category);

            $this->assignPackageNumber->handle($package, $project, $parent);

            $package->save();

            return $package;
        });
    }
}
