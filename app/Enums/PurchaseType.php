<?php

namespace App\Enums;

enum PurchaseType: string
{
    case Equipment = 'equipment';
    case Material = 'material';
    case Service = 'service';
    case Subcontract = 'subcontract';

    public function label(): string
    {
        return match ($this) {
            self::Equipment => __('Equipment'),
            self::Material => __('Material'),
            self::Service => __('Service'),
            self::Subcontract => __('Subcontract'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
