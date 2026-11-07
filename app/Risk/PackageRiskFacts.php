<?php

namespace App\Risk;

use App\Enums\PackageStatus;
use App\Scheduling\PackageSchedule;

/**
 * What the risk rules look at for one package.
 */
final readonly class PackageRiskFacts
{
    public function __construct(
        public PackageStatus $status,
        // The package's own milestones, for overdue dates and the need date.
        public PackageSchedule $schedule,
        // Counting sub-packages, as the package list shows it: a package isn't ready until they are.
        public ?int $floatWithSubPackages,
        public bool $isFlagged,
        public AssignedPerson $owner,
        public AssignedPerson $buyer,
    ) {}
}
