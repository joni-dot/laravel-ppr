<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddProjectMilestone
{
    /**
     * Keeps the schedule readable on a package page and in the package list.
     */
    public const int MAX_PER_PROJECT = 30;

    /**
     * Adds the milestone at the end of the project's list.
     *
     * @throws ValidationException
     */
    public function handle(Project $project, string $name): ProjectMilestone
    {
        return DB::transaction(function () use ($project, $name) {
            // Two people adding at once would otherwise both pass the limit and get the same position.
            Project::query()->whereKey($project->id)->lockForUpdate()->first();

            $list = ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->toBase()
                ->selectRaw('count(*) as count, max(position) as last_position')
                ->first();

            if ($list->count >= self::MAX_PER_PROJECT) {
                throw ValidationException::withMessages([
                    'name' => __('A project can have at most :max milestones.', ['max' => self::MAX_PER_PROJECT]),
                ]);
            }

            return $project->milestones()->create([
                'name' => $name,
                'position' => $list->last_position === null ? 0 : $list->last_position + 1,
            ]);
        });
    }
}
