<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case PartiallyIssued = 'partially_issued';
    case Fulfilled = 'fulfilled';
    case Released = 'released';
    case Cancelled = 'cancelled';
}
