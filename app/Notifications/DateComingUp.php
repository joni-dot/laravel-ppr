<?php

namespace App\Notifications;

use App\Models\Package;

/**
 * "Order placed is expected in 3 days." Sent once per milestone and expected date, when the date comes
 * within the person's own notification period.
 */
class DateComingUp extends PackageNotification
{
    public function __construct(
        Package $package,
        private readonly string $milestoneName,
        private readonly string $expectedDate,
        private readonly int $daysLeft,
    ) {
        parent::__construct($package);
    }

    public static function kind(): string
    {
        return 'date_coming_up';
    }

    /**
     * @return array{milestone: string, date: string, days_left: int}
     */
    protected function params(): array
    {
        return ['milestone' => $this->milestoneName, 'date' => $this->expectedDate, 'days_left' => $this->daysLeft];
    }
}
