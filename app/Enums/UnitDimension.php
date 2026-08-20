<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitDimension: string
{
    case Mass = 'mass';
    case Volume = 'volume';
    case Length = 'length';
    case Area = 'area';
    case Count = 'count';
    case Time = 'time';
}
