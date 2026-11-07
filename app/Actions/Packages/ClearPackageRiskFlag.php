<?php

namespace App\Actions\Packages;

use App\Models\Package;

/**
 * Takes away the high-risk mark someone set by hand. Other reasons for risk stay.
 */
class ClearPackageRiskFlag
{
    public function handle(Package $package): void
    {
        $package->forceFill([
            'risk_flag_reason' => null,
            'risk_flagged_by_id' => null,
            'risk_flagged_at' => null,
        ])->save();
    }
}
