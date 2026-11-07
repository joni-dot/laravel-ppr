<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveProjectMilestone
{
    /**
     * Removes the milestone together with every package's dates and lead time for it.
     * The need date stays, because lead times count back from it.
     *
     * @throws ValidationException
     */
    public function handle(ProjectMilestone $milestone): void
    {
        DB::transaction(function () use ($milestone) {
            // Waits for a need date being moved right now, so the check below sees the result.
            Project::query()->whereKey($milestone->project_id)->lockForUpdate()->first();
            $milestone->refresh();

            if ($milestone->is_need_date) {
                throw ValidationException::withMessages([
                    'milestone' => __('This milestone is the need date. Make another milestone the need date first.'),
                ]);
            }

            $milestone->delete();
        });
    }
}
