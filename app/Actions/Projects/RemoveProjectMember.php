<?php

namespace App\Actions\Projects;

use App\Models\ProjectMember;
use Illuminate\Support\Facades\DB;

class RemoveProjectMember
{
    public function __construct(
        private readonly EnsureProjectKeepsManager $ensureProjectKeepsManager,
        private readonly UnassignMemberFromPackages $unassignMemberFromPackages,
    ) {}

    public function handle(ProjectMember $member): void
    {
        DB::transaction(function () use ($member) {
            $this->ensureProjectKeepsManager->handle($member);

            $this->unassignMemberFromPackages->handle($member, newRole: null);

            $member->delete();
        });
    }
}
