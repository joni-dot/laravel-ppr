<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A PostgreSQL integer[] column, which the database returns as text like "{7,1,3}".
 *
 * @implements CastsAttributes<list<int>, list<int>>
 */
class IntegerArray implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $inner = trim((string) $value, '{}');

        return $inner === '' ? [] : array_map(intval(...), explode(',', $inner));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return '{'.implode(',', array_map(intval(...), $value)).'}';
    }
}
