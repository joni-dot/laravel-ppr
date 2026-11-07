<?php

namespace App\Actions\Projects;

use App\Models\ProjectMember;
use Illuminate\Support\Facades\DB;

class RemoveProjectMember
{
    public function __construct(private readonly EnsureProjectKeepsManager $ensureProjectKeepsManager) {}

    public function handle(ProjectMember $member): void
    {
        DB::transaction(function () use ($member) {
            $this->ensureProjectKeepsManager->handle($member);

            $member->delete();
        });
    }
}
