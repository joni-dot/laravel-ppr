<?php

namespace App\Dashboard;

use App\Enums\DateReminderKind;
use App\Enums\PackageStatus;
use App\Models\Project;
use App\Risk\PackageRisk;
use App\Risk\ProjectRisks;
use App\Risk\RiskLevel;
use App\Risk\RiskReason;
use App\Scheduling\Days;
use App\Scheduling\ProjectSchedules;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * What needs attention in one project: the dashboard's numbers, charts and personal actions. This is the
 * only place they are defined. Each number has a package list filter showing the same packages
 * (resources/js/lib/packageFilters.ts); the two must change together, and a browser test compares them.
 *
 * It reads the same rows, schedules and risks as the package list, so a number never disagrees with the
 * list's badges: three queries for the rows and their dates, however many packages there are.
 */
final readonly class ProjectAttention
{
    // "Due soon": a milestone not done yet is expected within this many days, today included.
    public const int DUE_SOON_DAYS = 14;

    // The "milestones due per week" chart covers this week and the ones after it.
    public const int CHART_WEEKS = 12;

    private const array PEOPLE_REASONS = [RiskReason::NoOwner, RiskReason::OwnerDeactivated, RiskReason::NoBuyer, RiskReason::BuyerDeactivated];

    /**
     * @param  list<stdClass>  $rows  rows()
     * @param  array<int, PackageRisk>  $risks  per package id
     * @param  string  $today  "YYYY-MM-DD" in UTC, as the risk rules use
     */
    private function __construct(
        private array $rows,
        private ProjectSchedules $schedules,
        private array $risks,
        private string $today,
    ) {}

    public static function for(Project $project): self
    {
        $rows = array_values(self::rows($project)->get()->all());
        $schedules = ProjectSchedules::for($project, $rows);

        return new self($rows, $schedules, ProjectRisks::fromRows($project, $rows, $schedules), now('UTC')->toDateString());
    }

    /**
     * The risk rules' rows with what the dashboard adds: category, number and title.
     */
    public static function rows(Project $project): Builder
    {
        return ProjectRisks::packageRows($project)->addSelect(['packages.category_id', 'packages.number', 'packages.title']);
    }

    /**
     * The headline numbers. Each counts packages, and each has a package list filter with the same rule.
     *
     * @return array{total: int, open: int, red: int, amber: int, overdue: int, dueSoon: int, missingPeople: int, noNeedDate: int, flagged: int}
     */
    public function tiles(): array
    {
        $tiles = ['total' => count($this->rows), 'open' => 0, 'red' => 0, 'amber' => 0, 'overdue' => 0, 'dueSoon' => 0, 'missingPeople' => 0, 'noNeedDate' => 0, 'flagged' => 0];

        foreach ($this->rows as $row) {
            $risk = $this->risks[$row->id];
            $tiles['open'] += (int) self::isOpen($row);
            $tiles['red'] += (int) ($risk->level === RiskLevel::Red);
            $tiles['amber'] += (int) ($risk->level === RiskLevel::Amber);
            $tiles['overdue'] += (int) $risk->has(RiskReason::Overdue);
            $tiles['dueSoon'] += (int) $this->isDueSoon($row);
            $tiles['missingPeople'] += (int) self::missesPeople($risk);
            $tiles['noNeedDate'] += (int) $risk->has(RiskReason::NoNeedDate);
            $tiles['flagged'] += (int) $risk->has(RiskReason::ManualFlag);
        }

        return $tiles;
    }

    /**
     * @return array<string, int> per status value, every status included, in the statuses' order
     */
    public function byStatus(): array
    {
        $counts = array_fill_keys(array_map(fn (PackageStatus $status) => $status->value, PackageStatus::cases()), 0);

        foreach ($this->rows as $row) {
            $counts[$row->status]++;
        }

        return $counts;
    }

    /**
     * Open packages per category and risk level. Closed and cancelled packages are left out: they are never
     * at risk, and counting them as "fine" would hide how much of a category is in trouble.
     *
     * @return list<array{categoryId: int|null, red: int, amber: int, none: int}> by category id, packages without a category last
     */
    public function riskByCategory(): array
    {
        $byCategory = [];

        foreach ($this->rows as $row) {
            if (! self::isOpen($row)) {
                continue;
            }

            $key = $row->category_id ?? 0;
            $byCategory[$key] ??= ['categoryId' => $row->category_id, 'red' => 0, 'amber' => 0, 'none' => 0];
            $byCategory[$key][$this->risks[$row->id]->level->value]++;
        }

        uksort($byCategory, fn (int $a, int $b) => ($a === 0) <=> ($b === 0) ?: $a <=> $b);

        return array_values($byCategory);
    }

    /**
     * Milestones of open packages that haven't happened and are expected in each week (Monday to Sunday),
     * from this week on. Overdue milestones are not counted: they are in the "overdue" number.
     *
     * @return list<array{weekStart: string, count: int}>
     */
    public function milestonesDuePerWeek(): array
    {
        $today = Days::fromDate($this->today);
        $firstMonday = $today - self::weekday($today);
        $counts = array_fill(0, self::CHART_WEEKS, 0);

        foreach ($this->rows as $row) {
            if (! self::isOpen($row)) {
                continue;
            }

            foreach ($this->schedules->of($row->id)->milestones() as $milestone) {
                if ($milestone->isDone() || $milestone->expected === null) {
                    continue;
                }

                $day = Days::fromDate($milestone->expected);
                $week = intdiv($day - $firstMonday, 7);

                if ($day >= $today && $week < self::CHART_WEEKS) {
                    $counts[$week]++;
                }
            }
        }

        return array_map(fn (int $week, int $count) => ['weekStart' => Days::toDate($firstMonday + 7 * $week), 'count' => $count], array_keys($counts), $counts);
    }

    /**
     * What one person must act on: milestones of the open packages they own or buy that are overdue, or
     * expected within their own notification period, by the same rule as their date reminders. Earliest first.
     *
     * @return list<array{packageId: int, number: string, title: string, milestoneName: string, date: string, daysLeft: int, kind: string, asOwner: bool, asBuyer: bool}>
     */
    public function actionsFor(int $userId, int $notificationDays): array
    {
        $today = Days::fromDate($this->today);
        $actions = [];

        foreach ($this->rows as $row) {
            if (! self::isOpen($row) || ($row->owner_id !== $userId && $row->buyer_id !== $userId)) {
                continue;
            }

            foreach ($this->schedules->of($row->id)->milestones() as $milestone) {
                if ($milestone->isDone() || $milestone->expected === null) {
                    continue;
                }

                $daysLeft = Days::fromDate($milestone->expected) - $today;
                $kind = DateReminderKind::forDaysLeft($daysLeft, $notificationDays);

                if ($kind !== null) {
                    $actions[] = [
                        'packageId' => $row->id,
                        'number' => $row->number,
                        'title' => $row->title,
                        'milestoneName' => $this->schedules->milestoneName($milestone->milestoneId),
                        'date' => $milestone->expected,
                        'daysLeft' => $daysLeft,
                        'kind' => $kind->value,
                        'asOwner' => $row->owner_id === $userId,
                        'asBuyer' => $row->buyer_id === $userId,
                    ];
                }
            }
        }

        usort($actions, fn (array $a, array $b) => [$a['date'], $a['number']] <=> [$b['date'], $b['number']]);

        return $actions;
    }

    private function isDueSoon(stdClass $row): bool
    {
        $nextDue = $this->schedules->of($row->id)->nextDue($this->today);

        return self::isOpen($row)
            && $nextDue !== null
            && Days::fromDate($nextDue->expected) - Days::fromDate($this->today) <= self::DUE_SOON_DAYS;
    }

    private static function isOpen(stdClass $row): bool
    {
        return $row->status !== PackageStatus::Closed->value && $row->status !== PackageStatus::Cancelled->value;
    }

    private static function missesPeople(PackageRisk $risk): bool
    {
        foreach (self::PEOPLE_REASONS as $reason) {
            if ($risk->has($reason)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 0 for Monday to 6 for Sunday. Day 0 of Days (1 January 1970) was a Thursday.
     */
    private static function weekday(int $day): int
    {
        return ($day + 3) % 7;
    }
}
