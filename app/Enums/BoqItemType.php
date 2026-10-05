<?php

declare(strict_types=1);

namespace App\Enums;

enum BoqItemType: string
{
    case Measured = 'measured';
    case LumpSum = 'lump_sum';
    case ProvisionalSum = 'provisional_sum';

    public function label(): string
    {
        return match ($this) {
            self::Measured => 'Measured work',
            self::LumpSum => 'Lump sum',
            self::ProvisionalSum => 'Provisional sum',
        };
    }
}
