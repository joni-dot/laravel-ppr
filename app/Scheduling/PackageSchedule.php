<?php

namespace App\Scheduling;

/**
 * Works out one package's expected and latest milestone dates. This is the only place these rules live.
 *
 * Expected date, going forward through the milestones: the actual date if it happened, else the forecast,
 * else the previous milestone's expected date plus this milestone's lead time, else the planned date.
 * A milestone that hasn't happened is expected no earlier than today: a missed date moves forward with
 * each day, so the float shrinks until someone marks it done. So a late order pushes every later
 * milestone back by itself.
 *
 * Due date (ScheduledMilestone::due()): the forecast, else the planned date. Overdue, due soon and the
 * date reminders count from it.
 *
 * Latest date, going backward from the need date (the planned date of the need-date milestone): each
 * earlier milestone must happen at least the next milestone's lead time before the next one's latest date.
 * A missing lead time leaves the earlier latest dates unknown rather than guessing zero days.
 *
 * Float: days between the expected need date and the planned need date. Below zero means late.
 */
final class PackageSchedule
{
    /**
     * @param  list<ScheduledMilestone>  $milestones
     */
    private function __construct(
        private readonly array $milestones,
        private readonly ?string $needDate,
        private readonly ?string $expectedNeedDate,
    ) {}

    /**
     * @param  array<int, MilestoneEntry>  $entries  keyed by project milestone id; missing ones count as empty
     * @param  string|null  $today  "YYYY-MM-DD" in UTC; null for a finished package, which nobody waits for, so
     *                              its missed dates stay where they were instead of moving with today
     */
    public static function calculate(MilestoneList $list, array $entries, ?string $today): self
    {
        $empty = new MilestoneEntry;
        $entriesInOrder = array_map(fn (int $id) => $entries[$id] ?? $empty, $list->ids);
        $expected = self::expectedDates($entriesInOrder, $today);
        $needIndex = $list->needDateId === null ? false : array_search($list->needDateId, $list->ids, true);
        $latest = $needIndex === false ? [] : self::latestDates($entriesInOrder, $needIndex);

        $milestones = [];

        foreach ($list->ids as $index => $id) {
            $milestones[] = new ScheduledMilestone($id, $entriesInOrder[$index], $expected[$index], $latest[$index] ?? null, $index === $needIndex);
        }

        return new self(
            $milestones,
            $needIndex === false ? null : $entriesInOrder[$needIndex]->planned,
            $needIndex === false ? null : $expected[$needIndex],
        );
    }

    /**
     * Days from the expected date to the need date: positive is time to spare, negative is late.
     */
    public static function floatDays(?string $needDate, ?string $expectedDate): ?int
    {
        if ($needDate === null || $expectedDate === null) {
            return null;
        }

        return Days::fromDate($needDate) - Days::fromDate($expectedDate);
    }

    /**
     * @return list<ScheduledMilestone>
     */
    public function milestones(): array
    {
        return $this->milestones;
    }

    /**
     * The first milestone that hasn't happened yet: the next thing someone has to act on.
     */
    public function next(): ?ScheduledMilestone
    {
        foreach ($this->milestones as $milestone) {
            if (! $milestone->isDone()) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * The milestone not done yet whose due date comes first from today on: the next date someone has to
     * meet. Unlike next(), it skips milestones without a due date and ones already overdue.
     *
     * @param  string  $today  "YYYY-MM-DD"
     */
    public function nextDue(string $today): ?ScheduledMilestone
    {
        $nextDue = null;

        foreach ($this->milestones as $milestone) {
            $due = $milestone->due();

            if (! $milestone->isDone() && $due !== null && $due >= $today
                && ($nextDue === null || $due < $nextDue->due())) {
                $nextDue = $milestone;
            }
        }

        return $nextDue;
    }

    /**
     * The first milestone that is overdue, if one is.
     *
     * @param  string  $today  "YYYY-MM-DD"
     */
    public function firstOverdue(string $today): ?ScheduledMilestone
    {
        foreach ($this->milestones as $milestone) {
            if ($milestone->isOverdue($today)) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * When the project needs the package: the planned date of the need-date milestone.
     */
    public function needDate(): ?string
    {
        return $this->needDate;
    }

    public function expectedNeedDate(): ?string
    {
        return $this->expectedNeedDate;
    }

    public function float(): ?int
    {
        return self::floatDays($this->needDate, $this->expectedNeedDate);
    }

    /**
     * @param  list<MilestoneEntry>  $entries
     * @return list<string|null>
     */
    private static function expectedDates(array $entries, ?string $today): array
    {
        $expected = [];
        $previous = null;

        foreach ($entries as $entry) {
            $date = $entry->forecast
                ?? ($previous !== null && $entry->leadDays !== null ? Days::add($previous, $entry->leadDays) : null)
                ?? $entry->planned;
            $previous = $entry->actual ?? ($today !== null && $date !== null && $date < $today ? $today : $date);
            $expected[] = $previous;
        }

        return $expected;
    }

    /**
     * @param  list<MilestoneEntry>  $entries
     * @return array<int, string|null>
     */
    private static function latestDates(array $entries, int $needIndex): array
    {
        $latest = [$needIndex => $entries[$needIndex]->planned];

        for ($index = $needIndex - 1; $index >= 0; $index--) {
            $next = $latest[$index + 1];
            $leadDays = $entries[$index + 1]->leadDays;
            $latest[$index] = $next !== null && $leadDays !== null ? Days::add($next, -$leadDays) : null;
        }

        return $latest;
    }
}
