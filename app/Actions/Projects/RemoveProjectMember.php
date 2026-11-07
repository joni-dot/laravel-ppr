<?php

namespace App\Actions\Projects;

use App\Models\Package;
use App\Models\ProjectMember;
use Illuminate\Support\Facades\DB;

class RemoveProjectMember
{
    public function __construct(private readonly EnsureProjectKeepsManager $ensureProjectKeepsManager) {}

    public function handle(ProjectMember $member): void
    {
        DB::transaction(function () use ($member) {
            $this->ensureProjectKeepsManager->handle($member);

            $this->unassignFromPackages($member);

            $member->delete();
        });
    }

    /**
     * Someone outside the project must not stay named as a package's owner or buyer.
     */
    private function unassignFromPackages(ProjectMember $member): void
    {
        Package::query()
            ->where('project_id', $member->project_id)
            ->where('owner_id', $member->user_id)
            ->update(['owner_id' => null]);

        Package::query()
            ->where('project_id', $member->project_id)
            ->where('buyer_id', $member->user_id)
            ->update(['buyer_id' => null]);
    }
}
