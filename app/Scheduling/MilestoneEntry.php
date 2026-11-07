<?php

namespace App\Scheduling;

/**
 * What has been filled in for one package and one milestone. Dates are "YYYY-MM-DD".
 */
final readonly class MilestoneEntry
{
    public function __construct(
        public ?string $planned = null,
        public ?string $forecast = null,
        public ?string $actual = null,
        // Calendar days from the previous milestone to this one.
        public ?int $leadDays = null,
    ) {}
}
