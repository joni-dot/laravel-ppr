<?php

namespace App\Enums;

/**
 * The system-wide role. Members get their real permissions from their role in each project.
 */
enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role) => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}
