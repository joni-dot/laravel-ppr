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
     *     today: string,
     * }
     */
    public static function for(Project $project, Package $package): array
    {
        $milestones = $project->milestones()->get(['id', 'name', 'is_need_date']);
        $list = MilestoneList::from($milestones);
        $tree = self::packageAndEverythingBelow($package);
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
            // The day the risk rules count overdue from, so the page's timeline marks the same today.
            'today' => now('UTC')->toDateString(),
        ];
    }

    /**
     * The package and all packages below it at any depth, in tree order.
     *
     * @return Collection<int, object{id: int, parent_id: int|null, status: string}>
     */
    private static function packageAndEverythingBelow(Package $package): Collection
    {
        /** @var Collection<int, object{id: int, parent_id: int|null, status: string}> */
        return $package->limitToItsTree(DB::table('packages'))
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
}
