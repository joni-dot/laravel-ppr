<?php

namespace App\Notifications;

use App\Enums\PackageAssignment;
use App\Models\Package;

/**
 * "You are now the owner (or responsible buyer) of this package."
 */
class PackageAssigned extends PackageNotification
{
    public function __construct(Package $package, private readonly PackageAssignment $assignment)
    {
        parent::__construct($package);
    }

    public static function kind(): string
    {
        return 'package_assigned';
    }

    /**
     * @return array{role: string}
     */
    protected function params(): array
    {
        return ['role' => $this->assignment->value];
    }
}
