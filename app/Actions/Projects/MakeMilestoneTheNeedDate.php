<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;

class MakeMilestoneTheNeedDate
{
    /**
     * Moves the need date to this milestone, so the project keeps exactly one.
     */
    public function handle(ProjectMilestone $milestone): void
    {
        DB::transaction(function () use ($milestone) {
            // Two people choosing different milestones at once could otherwise leave the project with none.
            Project::query()->whereKey($milestone->project_id)->lockForUpdate()->first();
            $milestone->refresh();

            ProjectMilestone::query()
                ->where('project_id', $milestone->project_id)
                ->where('is_need_date', true)
                ->whereKeyNot($milestone->id)
                ->update(['is_need_date' => false]);

            $milestone->is_need_date = true;
            $milestone->save();
        });
    }
}
