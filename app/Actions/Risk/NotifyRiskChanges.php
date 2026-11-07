<?php

namespace App\Actions\Risk;

use App\Models\Package;
use App\Models\Project;
use App\Notifications\ProjectRecipients;
use App\Notifications\RiskLevelChanged;
use App\Risk\PackageRisk;
use App\Risk\ProjectRisks;
use App\Risk\RiskLevel;
use App\Scheduling\ProjectSchedules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use stdClass;

/**
 * Tells the project managers and the package owner when a package's risk level has changed since they
 * were last told. packages.notified_risk_level remembers that level. A package checked for the first
 * time only gets its level stored, so a new package, or every package when the feature goes live,
 * doesn't flood anyone.
 */
class NotifyRiskChanges
{
    /**
     * @param  Collection<int, stdClass>  $rows  ProjectRisks::packageRows() with notified_risk_level, as read before this run changed anything
     * @param  int|null  $changedById  the person whose save started the check; they already know
     */
    public function handle(Project $project, Collection $rows, ProjectSchedules $schedules, ?int $changedById): void
    {
        $risks = ProjectRisks::fromRows($project, $rows, $schedules);
        $changes = $this->changedLevels($rows, $risks);

        if ($changes === []) {
            return;
        }

        $changedPackageIds = $this->storeLevels($changes);

        if ($changedPackageIds === []) {
            return;
        }

        $recipients = ProjectRecipients::of($project);
        $packages = $project->packages()->whereKey(array_keys($changedPackageIds))->get(['id', 'project_id', 'number', 'title']);
        $ownerIds = $rows->pluck('owner_id', 'id');

        foreach ($packages as $package) {
            $people = array_filter(
                $recipients->among([...$recipients->managerIds(), $ownerIds[$package->id]]),
                fn ($person) => $person->id !== $changedById,
            );

            Notification::send($people, $this->notification($package, $changedPackageIds[$package->id], $risks[$package->id], $schedules));
        }
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @param  array<int, PackageRisk>  $risks
     * @return list<array{id: int, previous: string|null, level: string}>
     */
    private function changedLevels(Collection $rows, array $risks): array
    {
        $changes = [];

        foreach ($rows as $row) {
            $level = $risks[$row->id]->level->value;

            if ($row->notified_risk_level !== $level) {
                $changes[] = ['id' => $row->id, 'previous' => $row->notified_risk_level, 'level' => $level];
            }
        }

        return $changes;
    }

    /**
     * Stores the new levels in one statement. A row changes only if it still has the level this run read,
     * so when two checks run at once only one of them gets the row back, and only that one tells people.
     *
     * @param  list<array{id: int, previous: string|null, level: string}>  $changes
     * @return array<int, RiskLevel> the level people were told before, per package id; first checks are left out
     */
    private function storeLevels(array $changes): array
    {
        $stored = DB::select(<<<'SQL'
            update packages
            set notified_risk_level = changes.level
            from json_to_recordset(?::json) as changes(id bigint, previous text, level text)
            where packages.id = changes.id
                and packages.notified_risk_level is not distinct from changes.previous
            returning packages.id, changes.previous
            SQL, [json_encode($changes, JSON_THROW_ON_ERROR)], useReadPdo: false);

        $previousLevels = [];

        foreach ($stored as $row) {
            if ($row->previous !== null) {
                $previousLevels[$row->id] = RiskLevel::from($row->previous);
            }
        }

        return $previousLevels;
    }

    private function notification(Package $package, RiskLevel $previous, PackageRisk $risk, ProjectSchedules $schedules): RiskLevelChanged
    {
        return new RiskLevelChanged(
            $package,
            $previous,
            $risk,
            floatDays: $schedules->floatWithSubPackages($package->id),
            overdueMilestoneName: $risk->overdueMilestone === null ? null : $schedules->milestoneName($risk->overdueMilestone->milestoneId),
        );
    }
}
