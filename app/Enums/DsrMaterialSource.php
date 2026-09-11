<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrMaterialSource: string
{
    case SiteStore = 'site_store';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::SiteStore => 'Site store',
            self::External => 'Supplied outside inventory',
        };
    }
}
