<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryMaterialClass: string
{
    case Consumable = 'consumable';
    case ConstructionMaterial = 'construction_material';
    case SparePart = 'spare_part';
    case FuelRelated = 'fuel_related';
    case Other = 'other';
}
