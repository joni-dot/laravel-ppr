<?php

namespace App\Actions\Projects;

use App\Models\Project;

/**
 * Gives a new project a starting list of milestones, which its project manager can then change.
 */
class AddDefaultMilestones
{
    public function handle(Project $project): void
    {
        $names = [
            __('Specification ready'),
            __('Request for quotation sent'),
            __('Offers received'),
            __('Order placed'),
            __('Delivered from supplier'),
            __('Required on site'),
            __('Installed / commissioned'),
        ];
        $needDateName = __('Required on site');

        foreach ($names as $position => $name) {
            $project->milestones()->create([
                'name' => $name,
                'position' => $position,
                'is_need_date' => $name === $needDateName,
            ]);
        }
    }
}
