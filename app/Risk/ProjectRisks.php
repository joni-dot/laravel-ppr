<?php

namespace App\Risk;

use App\Enums\PackageStatus;
use App\Models\Project;
use App\Scheduling\ProjectSchedules;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The risk of many packages of one project at once, with a constant number of queries however many
 * packages there are. The rules themselves are in PackageRisk.
 *
 * - ProjectRisks::for($project): every package of the project, three queries. For background jobs.
 * - ProjectRisks::fromRows($project, $rows, $schedules): when the rows and schedules are already
 *   loaded, as in the package list, so nothing is read twice. No queries.
 * - ProjectRisks::packageRows($project): the query for the rows, which a list can add its own columns to.
 */
final class ProjectRisks
{
    /**
     * Every package of the project.
     *
     * @return array<int, PackageRisk> per package id, in tree order
     */
    public static function for(Project $project): array
    {
        $rows = self::packageRows($project)->get();

        return self::fromRows($project, $rows, ProjectSchedules::for($project, $rows));
    }

    /**
     * The project's packages in tree order, with what the rules need: id, parent_id, status, owner_id,
     * buyer_id, owner_deactivated_at, buyer_deactivated_at and risk_flag_reason. The owners and buyers are
     * joined as "owners" and "buyers".
     */
    public static function packageRows(Project $project): Builder
    {
        return DB::table('packages')
            ->leftJoin('users as owners', 'owners.id', '=', 'packages.owner_id')
            ->leftJoin('users as buyers', 'buyers.id', '=', 'packages.buyer_id')
            ->where('packages.project_id', $project->id)
            ->orderBy('packages.number_path')
            ->select([
                'packages.id', 'packages.parent_id', 'packages.status', 'packages.owner_id', 'packages.buyer_id',
                'packages.risk_flag_reason', 'owners.deactivated_at as owner_deactivated_at', 'buyers.deactivated_at as buyer_deactivated_at',
            ]);
    }

    /**
     * @param  iterable<stdClass>  $rows  rows from packageRows(), in tree order
     * @param  ProjectSchedules  $schedules  the schedules of the same rows
     * @return array<int, PackageRisk> per package id, in the order of the rows
     */
    public static function fromRows(Project $project, iterable $rows, ProjectSchedules $schedules): array
    {
        $thresholds = RiskThresholds::of($project);
        $today = now('UTC')->toDateString();
        $risks = [];

        foreach ($rows as $row) {
            $risks[$row->id] = PackageRisk::assess(new PackageRiskFacts(
                status: PackageStatus::from($row->status),
                schedule: $schedules->of($row->id),
                floatWithSubPackages: $schedules->floatWithSubPackages($row->id),
                isFlagged: $row->risk_flag_reason !== null,
                owner: AssignedPerson::of($row->owner_id, $row->owner_deactivated_at),
                buyer: AssignedPerson::of($row->buyer_id, $row->buyer_deactivated_at),
            ), $thresholds, $today);
        }

        return $risks;
    }
}
