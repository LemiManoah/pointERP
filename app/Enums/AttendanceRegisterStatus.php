<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceRegisterStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
