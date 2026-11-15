<?php

namespace App\Http\Presenters;

use App\Dashboard\ProjectAttention;
use App\Enums\PackageStatus;
use App\Models\Project;
use App\Models\ProjectCategory;

/**
 * The project Overview tab's numbers and charts. ProjectAttention counts them; this only adds the names a
 * page shows. One query on top of ProjectAttention's, for the categories, however many packages there are.
 */
final class ProjectAttentionOverview
{
    /**
     * @return array{
     *     tiles: array{total: int, open: int, red: int, amber: int, overdue: int, dueSoon: int, missingPeople: int, noNeedDate: int, flagged: int},
     *     byStatus: list<array{value: string, label: string, count: int}>,
     *     riskByCategory: list<array{categoryId: int|null, code: string|null, name: string, red: int, amber: int, none: int}>,
     *     milestonesDuePerWeek: list<array{weekStart: string, count: int}>,
     *     dueSoonDays: int,
     * }
     */
    public static function for(Project $project): array
    {
        $attention = ProjectAttention::for($project);
        $countsByStatus = $attention->byStatus();

        return [
            'tiles' => $attention->tiles(),
            'byStatus' => array_map(fn (PackageStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => $countsByStatus[$status->value],
            ], PackageStatus::cases()),
            'riskByCategory' => self::withCategoryNames($project, $attention->riskByCategory()),
            'milestonesDuePerWeek' => $attention->milestonesDuePerWeek(),
            'dueSoonDays' => ProjectAttention::DUE_SOON_DAYS,
        ];
    }

    /**
     * @param  list<array{categoryId: int|null, red: int, amber: int, none: int}>  $counts
     * @return list<array{categoryId: int|null, code: string|null, name: string, red: int, amber: int, none: int}>
     */
    private static function withCategoryNames(Project $project, array $counts): array
    {
        if ($counts === []) {
            return [];
        }

        $categories = $project->categories()->get(['id', 'code', 'name'])->keyBy('id');

        return array_map(function (array $count) use ($categories) {
            /** @var ProjectCategory|null $category */
            $category = $count['categoryId'] === null ? null : $categories->get($count['categoryId']);

            return [
                'categoryId' => $count['categoryId'],
                'code' => $category?->code,
                'name' => $category->name ?? __('No category'),
                'red' => $count['red'],
                'amber' => $count['amber'],
                'none' => $count['none'],
            ];
        }, $counts);
    }
}
