<?php

namespace App\Risk;

use App\Models\Project;

/**
 * A project's risk warnings: a package turns amber or red when its float drops below these numbers of days.
 */
final readonly class RiskThresholds
{
    public function __construct(
        public int $amberDays,
        public int $redDays,
    ) {}

    public static function of(Project $project): self
    {
        return new self($project->risk_amber_days, $project->risk_red_days);
    }

    public function levelForFloat(?int $floatDays): RiskLevel
    {
        return match (true) {
            $floatDays === null => RiskLevel::None,
            $floatDays < $this->redDays => RiskLevel::Red,
            $floatDays < $this->amberDays => RiskLevel::Amber,
            default => RiskLevel::None,
        };
    }
}
