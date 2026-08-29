<?php

declare(strict_types=1);

namespace App\Enums;

enum EstimateResourceType: string
{
    case Material = 'material';
    case Labour = 'labour';
    case Equipment = 'equipment';
    case Subcontractor = 'subcontractor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Labour => 'Labour',
            self::Equipment => 'Equipment',
            self::Subcontractor => 'Subcontractor',
            self::Other => 'Other',
        };
    }
}
