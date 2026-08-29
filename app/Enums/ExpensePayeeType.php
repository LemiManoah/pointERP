<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpensePayeeType: string
{
    case Company = 'company';
    case Staff = 'staff';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
