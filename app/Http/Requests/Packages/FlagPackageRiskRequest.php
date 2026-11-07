<?php

namespace App\Http\Requests\Packages;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FlagPackageRiskRequest extends FormRequest
{
    public const int MAX_REASON_LENGTH = 500;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:'.self::MAX_REASON_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('Write why the package is at risk.'),
            'reason.string' => __('Write why the package is at risk.'),
            'reason.max' => __('Keep the reason to :max characters or fewer.', ['max' => self::MAX_REASON_LENGTH]),
        ];
    }
}
