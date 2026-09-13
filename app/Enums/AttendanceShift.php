<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceShift: string
{
    case Day = 'day';
    case Night = 'night';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
