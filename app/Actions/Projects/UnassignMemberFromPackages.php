<?php

namespace App\Actions\Projects;

use App\Enums\ProjectRole;
use App\Models\Package;
use App\Models\ProjectMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * Takes a member off the packages their new role no longer allows them to own or buy,
 * or off all of them when they leave the project. Otherwise a package would keep naming
 * someone it can't have, and nobody could save it without first choosing someone else.
 */
class UnassignMemberFromPackages
{
    /**
     * @param  ProjectRole|null  $newRole  null when the member leaves the project
     */
    public function handle(ProjectMember $member, ?ProjectRole $newRole): void
    {
        if (! in_array($newRole, ProjectRole::owningPackages(), true)) {
            $this->packagesOf($member, 'owner_id')->update(['owner_id' => null]);
        }

        if (! in_array($newRole, ProjectRole::buyingPackages(), true)) {
            $this->packagesOf($member, 'buyer_id')->update(['buyer_id' => null]);
        }
    }

    /**
     * @return Builder<Package>
     */
    private function packagesOf(ProjectMember $member, string $column): Builder
    {
        return Package::query()
            ->where('project_id', $member->project_id)
            ->where($column, $member->user_id);
    }
}
