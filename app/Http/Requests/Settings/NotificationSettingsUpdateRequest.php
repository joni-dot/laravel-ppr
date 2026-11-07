<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationSettingsUpdateRequest extends FormRequest
{
    /**
     * The database refuses anything above this too.
     */
    public const int MAX_DAYS = 90;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notification_days' => ['required', 'integer', 'min:0', 'max:'.self::MAX_DAYS],
        ];
    }
}
