<?php

namespace App\Scheduling;

use App\Enums\PackageStatus;
use App\Models\Project;
use stdClass;

/**
 * The schedules of many packages of one project, with each package's expected need date rolled up
 * from its sub-packages. Two queries, however many packages. The package list and the risk levels
 * both read it, so the schedule is worked out only once per page.
 */
final readonly class ProjectSchedules
{
    /**
     * @param  array<int, string>  $milestoneNames  per project milestone id
     * @param  array<int, PackageSchedule>  $schedules  per package id
     * @param  array<int, string|null>  $expectedNeedDatesWithSubPackages  per package id
     */
    private function __construct(
        private array $milestoneNames,
        private array $schedules,
        private array $expectedNeedDatesWithSubPackages,
    ) {}

    /**
     * @param  iterable<stdClass>  $packages  rows with id, parent_id and status, in tree order: every package before its sub-packages
     * @param  bool  $allOfTheProject  false reads the dates of only these packages, for a part of the tree
     */
    public static function for(Project $project, iterable $packages, bool $allOfTheProject = true): self
    {
        $packages = is_array($packages) ? $packages : iterator_to_array($packages, false);
        $milestones = $project->milestones()->get(['id', 'name', 'is_need_date']);
        $list = MilestoneList::from($milestones);
        $entries = MilestoneEntries::forProject($project->id, $allOfTheProject ? null : array_values(array_map(fn (stdClass $package): int => $package->id, $packages)));

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

        return new self(
            $milestones->pluck('name', 'id')->all(),
            $schedules,
            array_map(fn (array $dates) => $dates['expectedNeedDate'] ?? null, $rolledUp),
        );
    }

    /**
     * @return array<int, PackageSchedule> per package id, in tree order
     */
    public function all(): array
    {
        return $this->schedules;
    }

    public function of(int $packageId): PackageSchedule
    {
        return $this->schedules[$packageId];
    }

    /**
     * A package isn't ready until its sub-packages are, so this is the latest expected need date among them.
     */
    public function expectedNeedDateWithSubPackages(int $packageId): ?string
    {
        return $this->expectedNeedDatesWithSubPackages[$packageId] ?? null;
    }

    /**
     * The float the package list shows: the package's own need date against the latest expected need date below it.
     */
    public function floatWithSubPackages(int $packageId): ?int
    {
        return PackageSchedule::floatDays($this->schedules[$packageId]->needDate(), $this->expectedNeedDateWithSubPackages($packageId));
    }

    public function milestoneName(int $milestoneId): string
    {
        return $this->milestoneNames[$milestoneId];
    }
}
