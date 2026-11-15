<?php

namespace App\Scheduling;

use App\Enums\PackageStatus;
use App\Models\Project;
use stdClass;

/**
 * The schedules of many packages of one project, with each package's expected need date rolled up
 * from its sub-packages. Two queries, however many packages. The package list and the risk levels
 * both read it, so the schedule is worked out only once per page, and for one "today": everything
 * worked out from it counts from the same day, even when a request runs across midnight.
 *
 * A package is finished when it, or any package above it, is closed or cancelled: it is never at risk
 * and gets no reminders, while its own status stays as it is.
 */
final readonly class ProjectSchedules
{
    /**
     * @param  array<int, string>  $milestoneNames  per project milestone id
     * @param  array<int, PackageSchedule>  $schedules  per package id
     * @param  array<int, string|null>  $expectedNeedDatesWithSubPackages  per package id
     * @param  array<int, bool>  $finished  per package id
     */
    private function __construct(
        private array $milestoneNames,
        private array $schedules,
        private array $expectedNeedDatesWithSubPackages,
        private array $finished,
        public string $today,
    ) {}

    /**
     * @param  iterable<stdClass>  $packages  rows with id, parent_id and status, in tree order: every package before its sub-packages
     * @param  string  $today  "YYYY-MM-DD" in UTC; read once by the caller
     * @param  bool  $allOfTheProject  false reads the dates of only these packages, for a part of the tree
     * @param  bool  $belowAFinishedPackage  for a part of the tree: whether a package above its top is closed or cancelled
     */
    public static function for(Project $project, iterable $packages, string $today, bool $allOfTheProject = true, bool $belowAFinishedPackage = false): self
    {
        $packages = is_array($packages) ? $packages : iterator_to_array($packages, false);
        $milestones = $project->milestones()->get(['id', 'name', 'is_need_date']);
        $list = MilestoneList::from($milestones);
        $entries = MilestoneEntries::forProject($project->id, $allOfTheProject ? null : array_values(array_map(fn (stdClass $package): int => $package->id, $packages)));

        // Most packages of a new project have nothing filled in yet, and they all share the same empty schedule.
        // Empty schedules have no dates, so today changes nothing in them.
        $emptySchedule = PackageSchedule::calculate($list, [], $today);
        $finished = self::finishedIn($packages, $belowAFinishedPackage);
        $schedules = [];
        $rollUpRows = [];

        foreach ($packages as $package) {
            $schedule = isset($entries[$package->id])
                ? PackageSchedule::calculate($list, $entries[$package->id], $finished[$package->id] ? null : $today)
                : $emptySchedule;
            $schedules[$package->id] = $schedule;
            $rollUpRows[] = [
                'id' => $package->id,
                'parentId' => $package->parent_id,
                'isFinished' => $finished[$package->id],
                'dates' => ['expectedNeedDate' => $schedule->expectedNeedDate()],
            ];
        }

        $rolledUp = SubPackageRollUp::latest($rollUpRows);

        return new self(
            $milestones->pluck('name', 'id')->all(),
            $schedules,
            array_map(fn (array $dates) => $dates['expectedNeedDate'] ?? null, $rolledUp),
            $finished,
            $today,
        );
    }

    /**
     * Which packages are finished: closed or cancelled, or below one at any depth. The only place this is decided.
     *
     * @param  iterable<stdClass>  $packages  rows with id, parent_id and status, in tree order: every package before its sub-packages
     * @param  bool  $belowAFinishedPackage  for a part of the tree: whether a package above its top is closed or cancelled
     * @return array<int, bool> per package id
     */
    public static function finishedIn(iterable $packages, bool $belowAFinishedPackage = false): array
    {
        $finished = [];

        foreach ($packages as $package) {
            $finished[$package->id] = PackageStatus::from($package->status)->isFinished()
                || ($package->parent_id !== null && ($finished[$package->parent_id] ?? $belowAFinishedPackage));
        }

        return $finished;
    }

    /**
     * Closed or cancelled, itself or a package above it.
     */
    public function isFinished(int $packageId): bool
    {
        return $this->finished[$packageId];
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
