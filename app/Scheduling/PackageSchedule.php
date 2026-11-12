<?php

namespace App\Scheduling;

/**
 * Works out one package's expected and latest milestone dates. This is the only place these rules live.
 *
 * Expected date, going forward through the milestones: the actual date if it happened, else the forecast,
 * else the previous milestone's expected date plus this milestone's lead time, else the planned date.
 * So a late order pushes every later milestone back by itself.
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
     */
    public static function calculate(MilestoneList $list, array $entries): self
    {
        $empty = new MilestoneEntry;
        $entriesInOrder = array_map(fn (int $id) => $entries[$id] ?? $empty, $list->ids);
        $expected = self::expectedDates($entriesInOrder);
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
     * The milestone not done yet whose expected date comes first from today on: the next date someone has
     * to meet. Unlike next(), it skips milestones without a date and ones already overdue.
     *
     * @param  string  $today  "YYYY-MM-DD"
     */
    public function nextDue(string $today): ?ScheduledMilestone
    {
        $nextDue = null;

        foreach ($this->milestones as $milestone) {
            if (! $milestone->isDone() && $milestone->expected !== null && $milestone->expected >= $today
                && ($nextDue === null || $milestone->expected < $nextDue->expected)) {
                $nextDue = $milestone;
            }
        }

        return $nextDue;
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
    private static function expectedDates(array $entries): array
    {
        $expected = [];
        $previous = null;

        foreach ($entries as $entry) {
            $previous = $entry->actual
                ?? $entry->forecast
                ?? ($previous !== null && $entry->leadDays !== null ? Days::add($previous, $entry->leadDays) : null)
                ?? $entry->planned;
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
