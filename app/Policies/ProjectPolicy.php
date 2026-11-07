<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

/**
 * Admins can do everything in every project. Everyone else gets their rights
 * from their role in the project, and can't even see projects they don't belong to.
 */
class ProjectPolicy
{
    /**
     * Everyone may open the project list; it only shows the projects they can see.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->roleIn($project) !== null;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Change the project's details, risk warnings, numbering and categories.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->isAdminOrManager($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->isAdminOrManager($user, $project);
    }

    private function isAdminOrManager(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->roleIn($project) === ProjectRole::ProjectManager;
    }
}
