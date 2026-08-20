<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryMovementStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
}
