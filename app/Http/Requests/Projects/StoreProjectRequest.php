<?php

namespace App\Http\Requests\Projects;

use App\Concerns\ProjectValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    use ProjectValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->projectDetailRules(),
            // Every project starts with a project manager, so someone besides the admins can run it.
            'manager_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ];
    }
}
