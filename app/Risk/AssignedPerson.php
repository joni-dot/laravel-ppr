<?php

namespace App\Risk;

/**
 * Whether a package's owner or buyer is someone who can still act on it.
 */
enum AssignedPerson
{
    case Nobody;
    case Active;
    case Deactivated;

    public static function of(?int $userId, mixed $deactivatedAt): self
    {
        return match (true) {
            $userId === null => self::Nobody,
            $deactivatedAt !== null => self::Deactivated,
            default => self::Active,
        };
    }
}
