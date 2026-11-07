<?php

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\Project;
use LogicException;

/**
 * Gives a new package the next free number: the project's next running number for a
 * top-level package, or the parent's next sub-number for a sub-package.
 *
 * Must run inside a database transaction. The project row stays locked until it
 * ends, so two people adding packages at the same moment never get the same number.
 */
class AssignPackageNumber
{
    public function handle(Package $package, Project $project, ?Package $parent = null): void
    {
        if ($package->exists) {
            throw new LogicException('A package keeps the number it was given.');
        }

        // Read fresh, so the number follows the numbering settings saved right now.
        $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

        $parent === null
            ? $this->assignRunningNumber($package, $lockedProject)
            : $this->assignSubNumber($package, $lockedProject, $parent);
    }

    private function assignRunningNumber(Package $package, Project $lockedProject): void
    {
        $format = $lockedProject->numberFormat();
        $categoryCode = $package->category?->code;
        $runningNumber = $lockedProject->nextRunningNumber();

        // Numbering settings can change between packages, so in rare cases an old number
        // looks exactly like the next one. Skip ahead rather than show the same number twice.
        while ($this->isTaken($lockedProject, $format->format($runningNumber, $categoryCode))) {
            $runningNumber++;
        }

        $package->number = $format->format($runningNumber, $categoryCode);
        $package->number_path = [$runningNumber];

        $lockedProject->last_package_number = $runningNumber;
        $lockedProject->save();
    }

    private function assignSubNumber(Package $package, Project $project, Package $parent): void
    {
        $lockedParent = Package::query()->lockForUpdate()->findOrFail($parent->id);

        if ($lockedParent->project_id !== $project->id) {
            throw new LogicException('A sub-package must be in the same project as its parent.');
        }

        $format = $project->numberFormat();
        $subNumber = $lockedParent->last_sub_number + 1;

        while ($this->isTaken($project, $format->subPackageNumber($lockedParent->number, $subNumber))) {
            $subNumber++;
        }

        $package->parent_id = $lockedParent->id;
        $package->number = $format->subPackageNumber($lockedParent->number, $subNumber);
        $package->number_path = [...$lockedParent->number_path, $subNumber];

        $lockedParent->last_sub_number = $subNumber;
        $lockedParent->save();
    }

    private function isTaken(Project $project, string $number): bool
    {
        return Package::query()->where('project_id', $project->id)->where('number', $number)->exists();
    }
}
