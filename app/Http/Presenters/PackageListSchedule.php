<?php

namespace App\Http\Presenters;

use App\Scheduling\ProjectSchedules;

/**
 * The schedule columns of the package list.
 */
final class PackageListSchedule
{
    /**
     * The columns for each package, keyed by package id.
     *
     * @return array<int, array{nextMilestoneName: string|null, nextMilestoneDate: string|null, needDate: string|null, expectedNeedDate: string|null, floatDays: int|null}>
     */
    public static function for(ProjectSchedules $schedules): array
    {
        $columns = [];

        foreach ($schedules->all() as $id => $schedule) {
            $next = $schedule->next();

            $columns[$id] = [
                'nextMilestoneName' => $next === null ? null : $schedules->milestoneName($next->milestoneId),
                'nextMilestoneDate' => $next?->expected,
                'needDate' => $schedule->needDate(),
                // A package isn't ready until its sub-packages are, so its float comes from the latest of them.
                'expectedNeedDate' => $schedules->expectedNeedDateWithSubPackages($id),
                'floatDays' => $schedules->floatWithSubPackages($id),
            ];
        }

        return $columns;
    }
}
