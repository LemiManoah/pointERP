<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectEstimateStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved baseline',
            self::Superseded => 'Superseded',
        };
    }
}
