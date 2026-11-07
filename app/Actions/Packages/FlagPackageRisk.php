<?php

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\User;

/**
 * Marks a package as high risk by hand, or replaces the reason. The package turns red (see App\Risk\PackageRisk).
 */
class FlagPackageRisk
{
    public function handle(Package $package, User $flaggedBy, string $reason): void
    {
        $package->forceFill([
            'risk_flag_reason' => $reason,
            'risk_flagged_by_id' => $flaggedBy->id,
            'risk_flagged_at' => now(),
        ])->save();
    }
}
