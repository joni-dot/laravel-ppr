<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Like Laravel's "unique" rule, but "IT" and "it" count as the same value.
 */
class UniqueIgnoringCase implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  literal-string  $column  Fixed text from the code, never user input, because it becomes part of the SQL.
     */
    public function __construct(
        private readonly string $model,
        private readonly string $column,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isTaken = $this->model::query()
            ->whereRaw("lower({$this->column}) = lower(?)", [$value])
            ->when($this->ignoreId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($isTaken) {
            $fail('The :attribute has already been taken.');
        }
    }
}
