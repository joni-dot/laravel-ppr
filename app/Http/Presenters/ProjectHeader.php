<?php

namespace App\Http\Presenters;

use App\Models\Project;
use App\Models\User;

/**
 * What every project page needs for its title, breadcrumbs and tabs.
 */
final class ProjectHeader
{
    /**
     * @return array{id: int, name: string, code: string, can: array{update: bool, manageMembers: bool}}
     */
    public static function for(Project $project, User $user): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'can' => [
                'update' => $user->can('update', $project),
                'manageMembers' => $user->can('manageMembers', $project),
            ],
        ];
    }
}
