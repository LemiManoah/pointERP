<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryBatchStatus: string
{
    case Available = 'available';
    case Quarantined = 'quarantined';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
}
