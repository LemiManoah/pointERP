<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryTrackingType: string
{
    case None = 'none';
    case Serial = 'serial';
    case Batch = 'batch';
    case Other = 'other';
}
