<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrLabourAttendanceStatus: string
{
    case Matched = 'matched';
    case UnderReported = 'under_reported';
    case OverReported = 'over_reported';
    case MissingAttendance = 'missing_attendance';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::UnderReported => 'Under-reported',
            self::OverReported => 'Over-reported',
            self::MissingAttendance => 'Missing attendance',
        };
    }
}
