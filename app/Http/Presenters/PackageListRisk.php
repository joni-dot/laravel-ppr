<?php

namespace App\Http\Presenters;

use App\Risk\PackageRisk;
use App\Risk\RiskLevel;
use App\Risk\RiskReason;
use App\Scheduling\ProjectSchedules;

/**
 * The risk column of the package list. Kept small because the list has thousands of rows: nothing for a
 * package without risk, the reason codes for the page to put into words, and details only where needed.
 */
final class PackageListRisk
{
    /**
     * @param  string|null  $flagReason  why someone flagged the package, if they did
     * @return array{level: string, reasons: list<string>, overdueMilestone?: string, flagReason?: string}|null
     */
    public static function for(PackageRisk $risk, ?string $flagReason, ProjectSchedules $schedules): ?array
    {
        if ($risk->level === RiskLevel::None) {
            return null;
        }

        $column = [
            'level' => $risk->level->value,
            'reasons' => array_map(fn (RiskReason $reason) => $reason->value, $risk->reasons),
        ];

        if ($risk->overdueMilestone !== null) {
            $column['overdueMilestone'] = $schedules->milestoneName($risk->overdueMilestone->milestoneId);
        }

        if ($flagReason !== null && $risk->has(RiskReason::ManualFlag)) {
            $column['flagReason'] = $flagReason;
        }

        return $column;
    }
}
