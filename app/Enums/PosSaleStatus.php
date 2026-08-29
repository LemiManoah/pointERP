<?php

declare(strict_types=1);

namespace App\Enums;

enum PosSaleStatus: string
{
    case Draft = 'draft';
    case Held = 'held';
    case Completed = 'completed';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Held => 'Held',
            self::Completed => 'Completed',
            self::PartiallyReturned => 'Partially returned',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }
}
