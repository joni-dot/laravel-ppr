<?php

namespace App\Enums;

enum SettingKey: string
{
    /** A package turns amber when its spare time before it is needed drops below this many days. */
    case RiskAmberDays = 'risk_amber_days';

    /** A package turns red when its spare time drops below this many days. */
    case RiskRedDays = 'risk_red_days';

    /**
     * The value used until an admin saves their own, and for new projects.
     */
    public function defaultValue(): int
    {
        return match ($this) {
            self::RiskAmberDays => 7,
            self::RiskRedDays => 0,
        };
    }
}
