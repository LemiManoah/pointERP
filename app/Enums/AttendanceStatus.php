<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Sick = 'sick';
    case AuthorizedAbsence = 'authorized_absence';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Sick => 'Sick',
            self::AuthorizedAbsence => 'Authorized absence',
        };
    }

    public function countsHours(): bool
    {
        return $this === self::Present;
    }
}
