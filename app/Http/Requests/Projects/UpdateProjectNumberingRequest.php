<?php

namespace App\Http\Requests\Projects;

use App\Concerns\ProjectValidationRules;
use App\Enums\NumberSeparator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectNumberingRequest extends FormRequest
{
    use ProjectValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number_prefix' => ['nullable', 'string', 'max:10', self::CODE_PATTERN],
            'number_includes_project_code' => ['required', 'boolean'],
            'number_includes_category' => ['required', 'boolean'],
            'number_separator' => ['required', Rule::enum(NumberSeparator::class)],
            'number_padding' => ['required', 'integer', 'between:1,8'],
            'number_start' => ['required', 'integer', 'between:1,999999'],
            'sub_number_separator' => ['required', Rule::enum(NumberSeparator::class)->only(NumberSeparator::forSubNumbers())],
            'sub_number_padding' => ['required', 'integer', 'between:1,4'],
        ];
    }
}
