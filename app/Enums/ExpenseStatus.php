<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Corrected = 'corrected';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
