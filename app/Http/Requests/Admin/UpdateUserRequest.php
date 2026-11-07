<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}
