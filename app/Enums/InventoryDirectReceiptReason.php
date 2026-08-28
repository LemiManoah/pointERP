<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryDirectReceiptReason: string
{
    case OpeningBalance = 'opening_balance';
    case DirectPurchase = 'direct_purchase';
    case ClientSupplied = 'client_supplied';
    case SubcontractorSupplied = 'subcontractor_supplied';
    case Donation = 'donation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Opening balance',
            self::DirectPurchase => 'Direct purchase',
            self::ClientSupplied => 'Client-supplied stock',
            self::SubcontractorSupplied => 'Subcontractor-supplied stock',
            self::Donation => 'Donation',
            self::Other => 'Other authorised receipt',
        };
    }
}
