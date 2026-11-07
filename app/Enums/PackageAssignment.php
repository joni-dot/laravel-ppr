<?php

namespace App\Enums;

/**
 * The two ways a person is put in charge of a package.
 */
enum PackageAssignment: string
{
    case Owner = 'owner';
    case Buyer = 'buyer';

    /**
     * The package column that holds the person.
     */
    public function column(): string
    {
        return match ($this) {
            self::Owner => 'owner_id',
            self::Buyer => 'buyer_id',
        };
    }
}
