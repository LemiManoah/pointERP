<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
}
