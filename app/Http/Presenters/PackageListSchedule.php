<?php

namespace App\Http\Presenters;

use App\Enums\PackageStatus;
use App\Models\Project;
use App\Scheduling\MilestoneEntries;
use App\Scheduling\MilestoneList;
use App\Scheduling\PackageSchedule;
use App\Scheduling\SubPackageRollUp;
use stdClass;

/**
 * The schedule columns of the package list, for every package of a project with two queries.
 */
final class PackageListSchedule
{
    /**
     * The columns for each package, keyed by package id.
     *
     * @param  iterable<stdClass>  $packages  rows with id, parent_id and status, in tree order: every package before its sub-packages
     * @return array<int, array{nextMilestoneName: string|null, nextMilestoneDate: string|null, needDate: string|null, expectedNeedDate: string|null, floatDays: int|null}>
     */
    public static function for(Project $project, iterable $packages): array
    {
        $milestones = $project->milestones()->get(['id', 'name', 'is_need_date']);
        $names = $milestones->pluck('name', 'id')->all();
        $list = MilestoneList::from($milestones);
        $entries = MilestoneEntries::forProject($project->id);

        // Most packages of a new project have nothing filled in yet, and they all share the same empty schedule.
        $emptySchedule = PackageSchedule::calculate($list, []);
        $schedules = [];
        $rollUpRows = [];

        foreach ($packages as $package) {
            $schedule = isset($entries[$package->id]) ? PackageSchedule::calculate($list, $entries[$package->id]) : $emptySchedule;
            $schedules[$package->id] = $schedule;
            $rollUpRows[] = [
                'id' => $package->id,
                'parentId' => $package->parent_id,
                'isCancelled' => $package->status === PackageStatus::Cancelled->value,
                'dates' => ['expectedNeedDate' => $schedule->expectedNeedDate()],
            ];
        }

        $rolledUp = SubPackageRollUp::latest($rollUpRows);
        $columns = [];

        foreach ($schedules as $id => $schedule) {
            $next = $schedule->next();
            // A package isn't ready until its sub-packages are, so its float comes from the latest of them.
            $expectedNeedDate = $rolledUp[$id]['expectedNeedDate'] ?? null;

            $columns[$id] = [
                'nextMilestoneName' => $next === null ? null : $names[$next->milestoneId],
                'nextMilestoneDate' => $next?->expected,
                'needDate' => $schedule->needDate(),
                'expectedNeedDate' => $expectedNeedDate,
                'floatDays' => PackageSchedule::floatDays($schedule->needDate(), $expectedNeedDate),
            ];
        }

        return $columns;
    }
}
