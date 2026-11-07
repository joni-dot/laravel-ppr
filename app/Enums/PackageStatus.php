<?php

namespace App\Enums;

/**
 * Where a package is on its way from idea to delivery. Cancelled packages keep their
 * number, because package numbers are never reused.
 */
enum PackageStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Tendering = 'tendering';
    case Ordered = 'ordered';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Planned => __('Planned'),
            self::Tendering => __('Tendering'),
            self::Ordered => __('Ordered'),
            self::Delivered => __('Delivered'),
            self::Closed => __('Closed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
