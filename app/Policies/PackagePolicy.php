<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\Project;
use App\Models\User;

/**
 * Everyone in the project can look at its packages (see ProjectPolicy::view).
 * Admins, project managers and buyers add and edit all of them; package owners edit their own.
 */
class PackagePolicy
{
    /**
     * Add packages and sub-packages, and choose who owns and buys a package.
     */
    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin() || in_array($user->roleIn($project), ProjectRole::managingPackages(), true);
    }

    public function update(User $user, Package $package): bool
    {
        return $this->updateOwnedBy($user, $package->project, $package->owner_id);
    }

    /**
     * Change or clear a planned date that is already set. Planned dates are the original plan, so only
     * the people running the project move them; anyone who may update the package can set a missing one.
     */
    public function changePlannedDates(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->roleIn($project) === ProjectRole::ProjectManager;
    }

    /**
     * The rule behind update(), for lists that check thousands of packages without loading each one.
     */
    public function updateOwnedBy(User $user, Project $project, ?int $ownerId): bool
    {
        return $this->create($user, $project)
            || ($ownerId === $user->id && $user->roleIn($project) === ProjectRole::PackageOwner);
    }
}
