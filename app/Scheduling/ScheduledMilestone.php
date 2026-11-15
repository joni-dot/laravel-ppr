<?php

namespace App\Scheduling;

/**
 * One milestone of one package, with the dates worked out from what was filled in.
 */
final readonly class ScheduledMilestone
{
    public function __construct(
        public int $milestoneId,
        public MilestoneEntry $entry,
        // When it is now expected to happen. See PackageSchedule.
        public ?string $expected,
        // The last day it can happen without making the package late for its need date.
        public ?string $latest,
        public bool $isNeedDate,
    ) {}

    public function isDone(): bool
    {
        return $this->entry->actual !== null;
    }

    /**
     * The date someone committed to: the forecast, else the planned date. Overdue, due soon and the date
     * reminders count from it, not from the expected date, which is only an estimate from lead times.
     */
    public function due(): ?string
    {
        return $this->entry->forecast ?? $this->entry->planned;
    }

    /**
     * @param  string  $today  "YYYY-MM-DD"
     */
    public function isOverdue(string $today): bool
    {
        $due = $this->due();

        return ! $this->isDone() && $due !== null && $due < $today;
    }
}
