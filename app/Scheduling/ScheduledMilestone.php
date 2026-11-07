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
}
