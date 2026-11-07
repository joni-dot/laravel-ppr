<?php

namespace App\Http\Presenters;

use App\Models\Package;
use App\Models\Project;
use App\Risk\ProjectRisks;
use App\Risk\RiskReason;
use App\Risk\RiskThresholds;
use App\Scheduling\ProjectSchedules;

/**
 * A package's risk level and reasons for its page, and who flagged it by hand. Four queries at most,
 * however many sub-packages it has.
 */
final class PackageRiskOverview
{
    /**
     * @return array{
     *     level: string,
     *     reasons: list<string>,
     *     floatDays: int|null,
     *     thresholds: array{amberDays: int, redDays: int},
     *     overdueMilestone: array{name: string, expected: string|null}|null,
     *     flag: array{reason: string, flaggedByName: string|null, flaggedAt: string|null}|null,
     * }
     */
    public static function for(Project $project, Package $package): array
    {
        // Its sub-packages too, because its float counts them.
        $rows = $package->limitToItsTree(ProjectRisks::packageRows($project))->get();
        $schedules = ProjectSchedules::for($project, $rows, allOfTheProject: false);
        $risk = ProjectRisks::fromRows($project, $rows, $schedules)[$package->id];
        $thresholds = RiskThresholds::of($project);

        return [
            'level' => $risk->level->value,
            'reasons' => array_map(fn (RiskReason $reason) => $reason->value, $risk->reasons),
            'floatDays' => $schedules->floatWithSubPackages($package->id),
            'thresholds' => ['amberDays' => $thresholds->amberDays, 'redDays' => $thresholds->redDays],
            'overdueMilestone' => $risk->overdueMilestone === null ? null : [
                'name' => $schedules->milestoneName($risk->overdueMilestone->milestoneId),
                'expected' => $risk->overdueMilestone->expected,
            ],
            'flag' => self::flag($package),
        ];
    }

    /**
     * Shown even when the package is closed, so the people who may edit it can still clear the flag.
     *
     * @return array{reason: string, flaggedByName: string|null, flaggedAt: string|null}|null
     */
    private static function flag(Package $package): ?array
    {
        if ($package->risk_flag_reason === null) {
            return null;
        }

        $package->loadMissing('riskFlaggedBy:id,name');

        return [
            'reason' => $package->risk_flag_reason,
            'flaggedByName' => $package->riskFlaggedBy?->name,
            'flaggedAt' => $package->risk_flagged_at?->toIso8601String(),
        ];
    }
}
