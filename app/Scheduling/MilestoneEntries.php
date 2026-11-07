<?php

namespace App\Scheduling;

use Illuminate\Support\Facades\DB;

/**
 * Reads filled-in milestone values as plain rows, because a project's list needs tens of thousands at once.
 */
final class MilestoneEntries
{
    /**
     * @param  list<int>|null  $packageIds  only these packages, or every package of the project when null
     * @return array<int, array<int, MilestoneEntry>> per package id, per project milestone id
     */
    public static function forProject(int $projectId, ?array $packageIds = null): array
    {
        $rows = DB::table('package_milestones')
            ->join('packages', 'packages.id', '=', 'package_milestones.package_id')
            ->where('packages.project_id', $projectId)
            ->when($packageIds !== null, fn ($query) => $query->whereIn('package_milestones.package_id', $packageIds))
            ->get(['package_milestones.package_id', 'package_milestones.project_milestone_id', 'planned_date', 'forecast_date', 'actual_date', 'lead_days']);

        $entries = [];

        foreach ($rows as $row) {
            $entries[$row->package_id][$row->project_milestone_id] = new MilestoneEntry(
                $row->planned_date,
                $row->forecast_date,
                $row->actual_date,
                $row->lead_days,
            );
        }

        return $entries;
    }
}
