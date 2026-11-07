<?php

namespace App\Notifications;

use App\Models\Package;
use App\Risk\PackageRisk;
use App\Risk\RiskLevel;
use App\Risk\RiskReason;

/**
 * "Risk is now Red (was Amber): Late; No buyer." The reasons and their details are kept as they were
 * when the level changed, so the message doesn't change later.
 */
class RiskLevelChanged extends PackageNotification
{
    private readonly string $from;

    private readonly string $to;

    /** @var list<string> */
    private readonly array $reasons;

    private readonly ?string $overdueDate;

    /**
     * @param  int|null  $floatDays  counting sub-packages, for the "late" reason
     * @param  string|null  $overdueMilestoneName  for the "overdue" reason
     */
    public function __construct(
        Package $package,
        RiskLevel $from,
        PackageRisk $risk,
        private readonly ?int $floatDays,
        private readonly ?string $overdueMilestoneName,
    ) {
        parent::__construct($package);
        $this->from = $from->value;
        $this->to = $risk->level->value;
        $this->reasons = array_map(fn (RiskReason $reason) => $reason->value, $risk->reasons);
        $this->overdueDate = $risk->overdueMilestone?->expected;
    }

    public static function kind(): string
    {
        return 'risk_changed';
    }

    /**
     * @return array{from: string, to: string, reasons: list<string>, float_days: int|null, overdue_milestone: string|null, overdue_date: string|null}
     */
    protected function params(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'reasons' => $this->reasons,
            'float_days' => $this->floatDays,
            'overdue_milestone' => $this->overdueMilestoneName,
            'overdue_date' => $this->overdueDate,
        ];
    }
}
