<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The same limits apply to the company defaults and to each project's own warnings.
 */
trait RiskWarningRules
{
    private const int MAX_RISK_DAYS = 365;

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function riskWarningRules(): array
    {
        $range = 'between:-'.self::MAX_RISK_DAYS.','.self::MAX_RISK_DAYS;

        return [
            // Red is the more serious warning, so it must start closer to the deadline than amber.
            'risk_red_days' => ['required', 'integer', $range],
            'risk_amber_days' => ['required', 'integer', $range, 'gt:risk_red_days'],
        ];
    }
}
