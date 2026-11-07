<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower((string) $this->input('email'))]);
    }
}
