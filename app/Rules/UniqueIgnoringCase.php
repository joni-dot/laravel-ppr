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
     * @param  array<string, int|string>  $scope  Only compare with rows that have these values, such as ['project_id' => 5].
     */
    public function __construct(
        private readonly string $model,
        private readonly string $column,
        private readonly ?int $ignoreId = null,
        private readonly array $scope = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isTaken = $this->model::query()
            ->where($this->scope)
            ->whereRaw("lower({$this->column}) = lower(?)", [$value])
            ->when($this->ignoreId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($isTaken) {
            $fail('validation.unique')->translate();
        }
    }
}
