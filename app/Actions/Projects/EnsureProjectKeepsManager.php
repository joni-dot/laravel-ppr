<?php

namespace App\Actions\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Validation\ValidationException;

class EnsureProjectKeepsManager
{
    /**
     * Refuses to demote or remove the project's only project manager, so someone
     * besides the admins can always run the project. Call it inside a transaction:
     * it locks the project so two simultaneous changes can't both pass.
     *
     * @throws ValidationException
     */
    public function handle(ProjectMember $member): void
    {
        Project::query()->whereKey($member->project_id)->lockForUpdate()->first();
        $member->refresh();

        if ($member->role !== ProjectRole::ProjectManager) {
            return;
        }

        $otherManagers = ProjectMember::query()
            ->where('project_id', $member->project_id)
            ->where('role', ProjectRole::ProjectManager)
            ->whereKeyNot($member->id)
            ->exists();

        if (! $otherManagers) {
            throw ValidationException::withMessages([
                'role' => __('A project needs at least one project manager. Make someone else project manager first.'),
            ]);
        }
    }
}
