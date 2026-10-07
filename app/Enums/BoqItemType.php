<?php

declare(strict_types=1);

namespace App\Enums;

enum BoqItemType: string
{
    case Measured = 'measured';
    case LumpSum = 'lump_sum';
    case ProvisionalSum = 'provisional_sum';
    case PreliminaryFixed = 'preliminary_fixed';
    case PreliminaryTime = 'preliminary_time';
    case PercentageAdjustment = 'percentage_adjustment';
    case Daywork = 'daywork';

    public function label(): string
    {
        return match ($this) {
            self::Measured => 'Measured work',
            self::LumpSum => 'Lump sum',
            self::ProvisionalSum => 'Provisional sum',
            self::PreliminaryFixed => 'Fixed preliminary',
            self::PreliminaryTime => 'Time-based preliminary',
            self::PercentageAdjustment => 'Percentage adjustment',
            self::Daywork => 'Daywork',
        };
    }

    public function requiresSingleQuantity(): bool
    {
        return in_array($this, [self::LumpSum, self::ProvisionalSum, self::PreliminaryFixed, self::PercentageAdjustment], true);
    }
}
