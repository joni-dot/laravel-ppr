<?php

namespace App\Actions\Projects;

use App\Enums\MoveDirection;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;

class MoveProjectMilestone
{
    /**
     * Swaps the milestone with its neighbour. At the start or end of the list nothing changes.
     */
    public function handle(ProjectMilestone $milestone, MoveDirection $direction): void
    {
        DB::transaction(function () use ($milestone, $direction) {
            // Another change to the list at the same moment would otherwise work from an outdated order.
            Project::query()->whereKey($milestone->project_id)->lockForUpdate()->first();

            $positions = ProjectMilestone::query()
                ->where('project_id', $milestone->project_id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('position', 'id');

            $ids = $positions->keys()->all();
            $from = array_search($milestone->id, $ids, true);
            $to = $from === false ? null : $from + $direction->step();

            if ($to === null || ! isset($ids[$to])) {
                return;
            }

            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            // Numbering the whole list 0, 1, 2… also closes gaps left by removed milestones.
            foreach ($ids as $position => $id) {
                if ((int) $positions[$id] !== $position) {
                    ProjectMilestone::query()->whereKey($id)->update(['position' => $position]);
                }
            }
        });
    }
}
