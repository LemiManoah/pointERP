<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpensePaymentStatus: string
{
    case Recorded = 'recorded';
    case Reversed = 'reversed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
