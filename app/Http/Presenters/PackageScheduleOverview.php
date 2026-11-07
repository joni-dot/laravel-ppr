<?php

namespace App\Http\Presenters;

use App\Enums\PackageStatus;
use App\Models\Package;
use App\Models\Project;
use App\Scheduling\MilestoneEntries;
use App\Scheduling\MilestoneList;
use App\Scheduling\PackageSchedule;
use App\Scheduling\ScheduledMilestone;
use App\Scheduling\SubPackageRollUp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A package's milestone dates for its page, with the latest expected dates of its sub-packages.
 * Three queries, however many sub-packages there are.
 */
final class PackageScheduleOverview
{
    /**
     * @return array{
     *     milestones: list<array<string, mixed>>,
     *     needDate: string|null,
     *     expectedNeedDate: string|null,
     *     floatDays: int|null,
     *     withSubPackages: array{expectedNeedDate: string|null, floatDays: int|null}|null,
     * }
     */
    public static function for(Project $project, Package $package): array
    {
        $milestones = $project->milestones()->get(['id', 'name', 'is_need_date']);
        $list = MilestoneList::from($milestones);
        $tree = self::packageAndEverythingBelow($project, $package);
        $entries = MilestoneEntries::forProject($project->id, array_values(array_map(fn (object $row): int => $row->id, $tree->all())));
        $schedules = $tree->mapWithKeys(fn (object $row) => [$row->id => PackageSchedule::calculate($list, $entries[$row->id] ?? [])]);

        $schedule = $schedules[$package->id];
        $rolledUp = $tree->count() > 1 ? SubPackageRollUp::latest(self::rollUpRows($tree, $schedules))[$package->id] : null;
        $names = $milestones->pluck('name', 'id');

        return [
            'milestones' => array_map(fn (ScheduledMilestone $milestone) => [
                'id' => $milestone->milestoneId,
                'name' => $names[$milestone->milestoneId],
                'isNeedDate' => $milestone->isNeedDate,
                'planned' => $milestone->entry->planned,
                'forecast' => $milestone->entry->forecast,
                'actual' => $milestone->entry->actual,
                'leadDays' => $milestone->entry->leadDays,
                'expected' => $milestone->expected,
                'latest' => $milestone->latest,
                'isDone' => $milestone->isDone(),
                'expectedWithSubPackages' => $rolledUp[$milestone->milestoneId] ?? null,
            ], $schedule->milestones()),
            'needDate' => $schedule->needDate(),
            'expectedNeedDate' => $schedule->expectedNeedDate(),
            'floatDays' => $schedule->float(),
            'withSubPackages' => $rolledUp === null ? null : self::needDateWithSubPackages($schedule, $rolledUp, $list),
        ];
    }

    /**
     * The package and all packages below it at any depth, in tree order. Number paths sort like the tree,
     * so everything under [3, 2] lies between [3, 2] and [3, 3], and the project's number path index finds it.
     *
     * @return Collection<int, object{id: int, parent_id: int|null, status: string}>
     */
    private static function packageAndEverythingBelow(Project $project, Package $package): Collection
    {
        $path = $package->number_path;
        $nextSiblingPath = [...array_slice($path, 0, -1), $path[count($path) - 1] + 1];

        /** @var Collection<int, object{id: int, parent_id: int|null, status: string}> */
        return DB::table('packages')
            ->where('project_id', $project->id)
            ->whereRaw('number_path >= ?::integer[] and number_path < ?::integer[]', [self::arrayLiteral($path), self::arrayLiteral($nextSiblingPath)])
            ->orderBy('number_path')
            ->get(['id', 'parent_id', 'status']);
    }

    /**
     * @param  Collection<int, object{id: int, parent_id: int|null, status: string}>  $tree
     * @param  Collection<int, PackageSchedule>  $schedules
     * @return list<array{id: int, parentId: int|null, isCancelled: bool, dates: array<int, string|null>}>
     */
    private static function rollUpRows(Collection $tree, Collection $schedules): array
    {
        return array_values($tree->map(fn (object $row) => [
            'id' => $row->id,
            'parentId' => $row->parent_id,
            'isCancelled' => $row->status === PackageStatus::Cancelled->value,
            'dates' => self::expectedDates($schedules[$row->id]),
        ])->all());
    }

    /**
     * @return array<int, string|null> per project milestone id
     */
    private static function expectedDates(PackageSchedule $schedule): array
    {
        $dates = [];

        foreach ($schedule->milestones() as $milestone) {
            $dates[$milestone->milestoneId] = $milestone->expected;
        }

        return $dates;
    }

    /**
     * @param  array<int|string, string>  $rolledUp
     * @return array{expectedNeedDate: string|null, floatDays: int|null}
     */
    private static function needDateWithSubPackages(PackageSchedule $schedule, array $rolledUp, MilestoneList $list): array
    {
        $expected = $list->needDateId === null ? null : ($rolledUp[$list->needDateId] ?? null);

        return [
            'expectedNeedDate' => $expected,
            'floatDays' => PackageSchedule::floatDays($schedule->needDate(), $expected),
        ];
    }

    /**
     * @param  list<int>  $path
     */
    private static function arrayLiteral(array $path): string
    {
        return '{'.implode(',', $path).'}';
    }
}
