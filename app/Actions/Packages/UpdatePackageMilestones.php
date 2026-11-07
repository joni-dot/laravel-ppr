<?php

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\PackageMilestone;
use Illuminate\Support\Facades\DB;

/**
 * Saves a package's dates and lead times. Milestones left out keep theirs, and a milestone whose
 * values are all cleared loses its row, because a row exists only when something is filled in.
 */
class UpdatePackageMilestones
{
    /**
     * @param  list<array{id: int, planned_date: string|null, forecast_date: string|null, actual_date: string|null, lead_days: int|null}>  $rows
     * @param  bool  $mayChangePlannedDates  false keeps every planned date that is already set
     */
    public function handle(Package $package, array $rows, bool $mayChangePlannedDates): void
    {
        DB::transaction(function () use ($package, $rows, $mayChangePlannedDates) {
            // Two people saving the same package at once would otherwise both add the same row, and the
            // planned-date rule must see the dates as they are when this save writes.
            Package::query()->whereKey($package->id)->lockForUpdate()->value('id');

            $existing = $package->milestones()
                ->whereIn('project_milestone_id', array_column($rows, 'id'))
                ->get()
                ->keyBy('project_milestone_id');

            foreach ($rows as $row) {
                $milestone = $existing->get($row['id']) ?? $this->newMilestone($package, $row['id']);
                $this->fill($milestone, $row, $mayChangePlannedDates);
                $this->saveOrDelete($milestone);
            }
        });
    }

    private function newMilestone(Package $package, int $projectMilestoneId): PackageMilestone
    {
        $milestone = $package->milestones()->make();
        $milestone->project_milestone_id = $projectMilestoneId;

        return $milestone;
    }

    /**
     * @param  array{id: int, planned_date: string|null, forecast_date: string|null, actual_date: string|null, lead_days: int|null}  $row
     */
    private function fill(PackageMilestone $milestone, array $row, bool $mayChangePlannedDates): void
    {
        $milestone->fill([
            'forecast_date' => $row['forecast_date'],
            'actual_date' => $row['actual_date'],
            'lead_days' => $row['lead_days'],
        ]);

        if ($mayChangePlannedDates || $milestone->planned_date === null) {
            $milestone->fill(['planned_date' => $row['planned_date']]);
        }
    }

    private function saveOrDelete(PackageMilestone $milestone): void
    {
        if (! $milestone->isEmpty()) {
            $milestone->save();
        } elseif ($milestone->exists) {
            $milestone->delete();
        }
    }
}
