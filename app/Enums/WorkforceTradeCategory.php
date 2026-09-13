<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkforceTradeCategory: string
{
    case Skilled = 'skilled';
    case SemiSkilled = 'semi_skilled';
    case Unskilled = 'unskilled';
    case Specialist = 'specialist';

    public function label(): string
    {
        return match ($this) {
            self::Skilled => 'Skilled',
            self::SemiSkilled => 'Semi-skilled',
            self::Unskilled => 'Unskilled',
            self::Specialist => 'Specialist',
        };
    }
}
