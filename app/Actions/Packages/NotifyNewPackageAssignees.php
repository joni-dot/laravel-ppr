<?php

namespace App\Actions\Packages;

use App\Enums\PackageAssignment;
use App\Models\Package;
use App\Models\User;
use App\Notifications\PackageAssigned;

/**
 * Tells the people who just became a package's owner or responsible buyer. Runs right after the
 * package is saved, because only then does the package still know which of its values changed.
 */
class NotifyNewPackageAssignees
{
    public function handle(Package $package, User $changedBy): void
    {
        $assignments = $this->newAssignments($package, $changedBy);

        if ($assignments === []) {
            return;
        }

        $people = User::query()->notDeactivated()->findMany(array_column($assignments, 'userId'))->keyBy('id');

        foreach ($assignments as ['userId' => $userId, 'assignment' => $assignment]) {
            $people->get($userId)?->notify(new PackageAssigned($package, $assignment));
        }
    }

    /**
     * People are not told about a change they made themselves.
     *
     * @return list<array{userId: int, assignment: PackageAssignment}>
     */
    private function newAssignments(Package $package, User $changedBy): array
    {
        $assignments = [];

        foreach (PackageAssignment::cases() as $assignment) {
            $userId = $package->getAttribute($assignment->column());

            if ($package->wasChanged($assignment->column()) && $userId !== null && (int) $userId !== $changedBy->id) {
                $assignments[] = ['userId' => (int) $userId, 'assignment' => $assignment];
            }
        }

        return $assignments;
    }
}
