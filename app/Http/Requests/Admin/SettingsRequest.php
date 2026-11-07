<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    private const int MAX_DAYS = 365;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Red is the more serious warning, so it must start closer to the deadline than amber.
            'risk_red_days' => ['required', 'integer', 'between:-'.self::MAX_DAYS.','.self::MAX_DAYS],
            'risk_amber_days' => ['required', 'integer', 'between:-'.self::MAX_DAYS.','.self::MAX_DAYS, 'gt:risk_red_days'],
        ];
    }
}
