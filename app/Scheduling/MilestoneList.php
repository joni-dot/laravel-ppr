<?php

namespace App\Scheduling;

use App\Models\ProjectMilestone;
use Illuminate\Support\Collection;

/**
 * A project's milestones in order, and which of them is the need date.
 */
final readonly class MilestoneList
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(
        public array $ids,
        public ?int $needDateId,
    ) {}

    /**
     * @param  Collection<int, ProjectMilestone>  $milestones  in order
     */
    public static function from(Collection $milestones): self
    {
        return new self(
            array_values($milestones->map(fn (ProjectMilestone $milestone) => $milestone->id)->all()),
            $milestones->firstWhere('is_need_date', true)?->id,
        );
    }
}
