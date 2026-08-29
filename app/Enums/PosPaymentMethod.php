<?php

declare(strict_types=1);

namespace App\Enums;

enum PosPaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::MobileMoney => 'Mobile money',
            self::Card => 'Card',
            self::Bank => 'Bank transfer',
        };
    }
}
