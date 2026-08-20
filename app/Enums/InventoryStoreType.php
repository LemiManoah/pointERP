<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryStoreType: string
{
    case Warehouse = 'warehouse';
    case Depot = 'depot';
    case SiteStore = 'site_store';
    case Temporary = 'temporary';
    case Other = 'other';
}
