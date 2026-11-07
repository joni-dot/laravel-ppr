<?php

namespace App\Notifications;

use App\Models\Package;

/**
 * "Order placed was expected on 3 Nov and isn't marked done." Sent once per milestone and expected date.
 */
class DateOverdue extends PackageNotification
{
    public function __construct(
        Package $package,
        private readonly string $milestoneName,
        private readonly string $expectedDate,
    ) {
        parent::__construct($package);
    }

    public static function kind(): string
    {
        return 'date_overdue';
    }

    /**
     * @return array{milestone: string, date: string}
     */
    protected function params(): array
    {
        return ['milestone' => $this->milestoneName, 'date' => $this->expectedDate];
    }
}
