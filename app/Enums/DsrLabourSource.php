<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrLabourSource: string
{
    case Internal = 'internal';
    case Casual = 'casual';
    case Subcontractor = 'subcontractor';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal staff',
            self::Casual => 'Casual labour',
            self::Subcontractor => 'Subcontractor',
        };
    }
}
