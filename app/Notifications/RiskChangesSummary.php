<?php

namespace App\Notifications;

use App\Models\Project;
use App\Risk\RiskLevel;

/**
 * "Risk level changed for 14 packages in Harbour crane: 5 now red, 6 now amber, 3 no longer at risk."
 * Sent instead of one RiskLevelChanged per package when one check would send a person too many of them.
 * Opening it goes to the project's package list.
 */
class RiskChangesSummary extends ProjectNotification
{
    private readonly int $red;

    private readonly int $amber;

    private readonly int $none;

    /**
     * @param  list<RiskLevel>  $newLevels  one per changed package
     */
    public function __construct(Project $project, array $newLevels)
    {
        parent::__construct($project->id);
        $counts = array_count_values(array_map(fn (RiskLevel $level) => $level->value, $newLevels));
        $this->red = $counts[RiskLevel::Red->value] ?? 0;
        $this->amber = $counts[RiskLevel::Amber->value] ?? 0;
        $this->none = $counts[RiskLevel::None->value] ?? 0;
    }

    public static function kind(): string
    {
        return 'risk_changes_summary';
    }

    /**
     * @return array{changed: int, red: int, amber: int, none: int}
     */
    protected function params(): array
    {
        return [
            'changed' => $this->red + $this->amber + $this->none,
            'red' => $this->red,
            'amber' => $this->amber,
            'none' => $this->none,
        ];
    }
}
