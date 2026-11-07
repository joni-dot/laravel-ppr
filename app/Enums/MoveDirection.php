<?php

namespace App\Enums;

enum MoveDirection: string
{
    case Up = 'up';
    case Down = 'down';

    /**
     * How the position in a list changes: up is towards the start.
     */
    public function step(): int
    {
        return match ($this) {
            self::Up => -1,
            self::Down => 1,
        };
    }
}
