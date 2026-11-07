<?php

namespace App\Risk;

use App\Enums\PackageStatus;
use App\Scheduling\PackageSchedule;
use App\Scheduling\ScheduledMilestone;

/**
 * One package's risk level and the reasons for it. This is the only place the risk rules live;
 * pages, lists and notifications show its results. Worked out when asked, never stored for display.
 *
 * - Closed and cancelled packages are never at risk.
 * - Red: flagged by hand; a milestone that hasn't happened was expected before today; or the float
 *   (counting sub-packages) is below the project's red warning.
 * - Amber: the float is below the amber warning; the need date isn't planned, so the float can't be
 *   judged; or the package has no owner or buyer, or theirs is deactivated.
 * - The level is the worst of the reasons. Only the float counts sub-packages; the other reasons
 *   are about the package itself.
 */
final readonly class PackageRisk
{
    /**
     * @param  list<RiskReason>  $reasons  in the order of RiskReason's cases
     */
    private function __construct(
        public RiskLevel $level,
        public array $reasons,
        // The first milestone that is overdue, when one is.
        public ?ScheduledMilestone $overdueMilestone = null,
    ) {}

    /**
     * @param  string  $today  "YYYY-MM-DD"; milestones expected before it are overdue
     */
    public static function assess(PackageRiskFacts $facts, RiskThresholds $thresholds, string $today): self
    {
        if ($facts->status === PackageStatus::Closed || $facts->status === PackageStatus::Cancelled) {
            return new self(RiskLevel::None, []);
        }

        $overdue = self::firstOverdueMilestone($facts->schedule, $today);
        $lateness = $thresholds->levelForFloat($facts->floatWithSubPackages);
        $found = array_filter([
            [RiskReason::ManualFlag, $facts->isFlagged ? RiskLevel::Red : null],
            [RiskReason::Overdue, $overdue === null ? null : RiskLevel::Red],
            [RiskReason::Late, $lateness === RiskLevel::None ? null : $lateness],
            [RiskReason::NoNeedDate, $facts->schedule->needDate() === null ? RiskLevel::Amber : null],
            [RiskReason::NoOwner, $facts->owner === AssignedPerson::Nobody ? RiskLevel::Amber : null],
            [RiskReason::OwnerDeactivated, $facts->owner === AssignedPerson::Deactivated ? RiskLevel::Amber : null],
            [RiskReason::NoBuyer, $facts->buyer === AssignedPerson::Nobody ? RiskLevel::Amber : null],
            [RiskReason::BuyerDeactivated, $facts->buyer === AssignedPerson::Deactivated ? RiskLevel::Amber : null],
        ], fn (array $reasonAndLevel) => $reasonAndLevel[1] !== null);

        return new self(self::worst(array_column($found, 1)), array_column($found, 0), $overdue);
    }

    public function has(RiskReason $reason): bool
    {
        return in_array($reason, $this->reasons, true);
    }

    /**
     * A milestone is overdue when it hasn't happened and its expected date is already past.
     */
    private static function firstOverdueMilestone(PackageSchedule $schedule, string $today): ?ScheduledMilestone
    {
        foreach ($schedule->milestones() as $milestone) {
            if (! $milestone->isDone() && $milestone->expected !== null && $milestone->expected < $today) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * @param  list<RiskLevel>  $levels
     */
    private static function worst(array $levels): RiskLevel
    {
        $worst = RiskLevel::None;

        foreach ($levels as $level) {
            if ($level->isWorseThan($worst)) {
                $worst = $level;
            }
        }

        return $worst;
    }
}
