<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Return = 'return';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';
    case OpeningBalance = 'opening_balance';
    case Reversal = 'reversal';
}
