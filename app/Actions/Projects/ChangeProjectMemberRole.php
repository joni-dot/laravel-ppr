<?php

namespace App\Actions\Projects;

use App\Enums\ProjectRole;
use App\Models\ProjectMember;
use Illuminate\Support\Facades\DB;

class ChangeProjectMemberRole
{
    public function __construct(private readonly EnsureProjectKeepsManager $ensureProjectKeepsManager) {}

    public function handle(ProjectMember $member, ProjectRole $role): void
    {
        if ($member->role === $role) {
            return;
        }

        DB::transaction(function () use ($member, $role) {
            $this->ensureProjectKeepsManager->handle($member);

            $member->role = $role;
            $member->save();
        });
    }
}
