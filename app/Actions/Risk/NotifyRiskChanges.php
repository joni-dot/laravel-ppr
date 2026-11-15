<?php

namespace App\Actions\Risk;

use App\Models\Package;
use App\Models\Project;
use App\Notifications\ProjectRecipients;
use App\Notifications\RiskChangesSummary;
use App\Notifications\RiskLevelChanged;
use App\Risk\PackageRisk;
use App\Risk\ProjectRisks;
use App\Risk\RiskLevel;
use App\Scheduling\ProjectSchedules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Tells the project managers and the package owner when a package's risk level has changed since they
 * were last told. packages.notified_risk_level remembers that level. A package checked for the first
 * time only gets its level stored, so a new package, or every package when the feature goes live,
 * doesn't flood anyone. A person who would get more than MOST_SINGLE_NOTICES notices from one check
 * gets one RiskChangesSummary instead.
 */
class NotifyRiskChanges
{
    // Ten notices still read as news about each package; more, for example after the project's risk
    // warnings change, would bury everything else on the Notifications page.
    public const int MOST_SINGLE_NOTICES = 10;

    /**
     * @param  Collection<int, stdClass>  $rows  ProjectRisks::packageRows() with notified_risk_level, as read before this run changed anything
     * @param  int|null  $changedById  the person whose save started the check; they already know about what their save changed
     * @param  list<int>|null  $savedPackageIds  the packages that save changed; null when it changed the whole project
     */
    public function handle(Project $project, Collection $rows, ProjectSchedules $schedules, ?int $changedById, ?array $savedPackageIds = null): void
    {
        $risks = ProjectRisks::fromRows($project, $rows, $schedules);
        $changes = $this->changedLevels($rows, $risks);

        if ($changes === []) {
            return;
        }

        $previousLevels = $this->storeLevels($changes);

        if ($previousLevels === []) {
            return;
        }

        $recipients = ProjectRecipients::of($project);
        $changedBySave = $changedById === null ? [] : $this->changedBySave($rows, array_keys($previousLevels), $savedPackageIds);
        $packageIdsPerPerson = $this->packageIdsPerPerson($rows, array_keys($previousLevels), $recipients, $changedById, $changedBySave);
        $packages = $this->packagesToTellOneByOne($project, $packageIdsPerPerson);

        foreach ($packageIdsPerPerson as $personId => $packageIds) {
            [$person] = $recipients->among([$personId]);

            if (count($packageIds) > self::MOST_SINGLE_NOTICES) {
                $person->notify(new RiskChangesSummary($project, array_map(fn (int $id) => $risks[$id]->level, $packageIds)));
            } else {
                foreach ($packageIds as $packageId) {
                    $person->notify($this->notification($packages[$packageId], $previousLevels[$packageId], $risks[$packageId], $schedules));
                }
            }
        }
    }

    /**
     * Who is told about which changed packages: the project managers and the package's owner, each once,
     * leaving out the person who saved for the packages their save changed.
     *
     * @param  Collection<int, stdClass>  $rows
     * @param  list<int>  $changedPackageIds
     * @param  array<int, true>  $changedBySave  per package id, the packages the saver isn't told about
     * @return array<int, list<int>> changed package ids per person id
     */
    private function packageIdsPerPerson(Collection $rows, array $changedPackageIds, ProjectRecipients $recipients, ?int $changedById, array $changedBySave): array
    {
        $ownerIds = $rows->pluck('owner_id', 'id');
        $perPerson = [];

        foreach ($changedPackageIds as $packageId) {
            foreach ($recipients->among([...$recipients->managerIds(), $ownerIds[$packageId]]) as $person) {
                if ($person->id !== $changedById || ! isset($changedBySave[$packageId])) {
                    $perPerson[$person->id][] = $packageId;
                }
            }
        }

        return $perPerson;
    }

    /**
     * The changed packages the save itself changed: everything when it changed the whole project (null),
     * else the saved packages, the packages above them, whose float counts their sub-packages, and the
     * packages below them, which count as finished once their parent is closed or cancelled.
     * A change elsewhere in the project, such as a date passing overnight, is news to the saver too.
     *
     * @param  Collection<int, stdClass>  $rows
     * @param  list<int>  $changedPackageIds
     * @param  list<int>|null  $savedPackageIds
     * @return array<int, true> per package id
     */
    private function changedBySave(Collection $rows, array $changedPackageIds, ?array $savedPackageIds): array
    {
        if ($savedPackageIds === null) {
            return array_fill_keys($changedPackageIds, true);
        }

        $parentIds = $rows->pluck('parent_id', 'id');
        $childIds = $rows->whereNotNull('parent_id')->groupBy('parent_id')->map(fn (Collection $children) => $children->pluck('id')->all());
        $affected = [];

        foreach ($savedPackageIds as $savedId) {
            for ($id = $parentIds[$savedId] ?? null; $id !== null && ! isset($affected[$id]); $id = $parentIds[$id]) {
                $affected[$id] = true;
            }

            $below = [$savedId];

            while ($below !== []) {
                $id = array_pop($below);
                $affected[$id] = true;
                array_push($below, ...($childIds[$id] ?? []));
            }
        }

        return $affected;
    }

    /**
     * @param  array<int, list<int>>  $packageIdsPerPerson
     * @return Collection<int, Package> per id, only the packages someone is told about one by one
     */
    private function packagesToTellOneByOne(Project $project, array $packageIdsPerPerson): Collection
    {
        $ids = [];

        foreach ($packageIdsPerPerson as $packageIds) {
            if (count($packageIds) <= self::MOST_SINGLE_NOTICES) {
                $ids += array_fill_keys($packageIds, true);
            }
        }

        if ($ids === []) {
            return new Collection;
        }

        return $project->packages()->whereKey(array_keys($ids))->get(['id', 'project_id', 'number', 'title'])->keyBy('id');
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
