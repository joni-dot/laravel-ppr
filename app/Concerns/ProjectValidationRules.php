<?php

namespace App\Concerns;

use App\Models\Project;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;

trait ProjectValidationRules
{
    /**
     * Codes become part of package numbers, so only letters and digits are allowed:
     * a separator character inside a code would make numbers ambiguous.
     */
    protected const string CODE_PATTERN = 'regex:/^[A-Za-z0-9]+$/';

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function projectDetailRules(?int $ignoreProjectId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:10', self::CODE_PATTERN, new UniqueIgnoringCase(Project::class, 'code', $ignoreProjectId)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
