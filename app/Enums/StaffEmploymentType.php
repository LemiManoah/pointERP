<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffEmploymentType: string
{
    case Permanent = 'permanent';
    case FixedTerm = 'fixed_term';
    case Casual = 'casual';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::FixedTerm => 'Fixed-term',
            self::Casual => 'Casual',
        };
    }
}
