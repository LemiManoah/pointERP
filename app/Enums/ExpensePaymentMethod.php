<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank transfer',
            self::MobileMoney => 'Mobile money',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }
}
