<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectType: string
{
    case BuildingConstruction = 'building_construction';
    case RoadConstruction = 'road_construction';
    case BridgeConstruction = 'bridge_construction';
    case WaterSupply = 'water_supply';
    case Sewerage = 'sewerage';
    case Drainage = 'drainage';
    case ElectricalInfrastructure = 'electrical_infrastructure';
    case CivilInfrastructure = 'civil_infrastructure';
    case Renovation = 'renovation';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BuildingConstruction => 'Building construction',
            self::RoadConstruction => 'Road construction',
            self::BridgeConstruction => 'Bridge construction',
            self::WaterSupply => 'Water supply',
            self::Sewerage => 'Sewerage',
            self::Drainage => 'Drainage',
            self::ElectricalInfrastructure => 'Electrical infrastructure',
            self::CivilInfrastructure => 'Civil infrastructure',
            self::Renovation => 'Renovation',
            self::Maintenance => 'Maintenance',
            self::Other => 'Other',
        };
    }
}
