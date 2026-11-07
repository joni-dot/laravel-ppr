<?php

namespace App\Risk;

/**
 * How worried the project should be about a package. See PackageRisk for the rules.
 */
enum RiskLevel: string
{
    case None = 'none';
    case Amber = 'amber';
    case Red = 'red';

    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    private function severity(): int
    {
        return match ($this) {
            self::None => 0,
            self::Amber => 1,
            self::Red => 2,
        };
    }
}
