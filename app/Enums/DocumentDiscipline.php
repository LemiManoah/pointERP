<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentDiscipline: string
{
    case General = 'general';
    case Civil = 'civil';
    case Structural = 'structural';
    case Architectural = 'architectural';
    case Geotechnical = 'geotechnical';
    case Survey = 'survey';
    case Mechanical = 'mechanical';
    case Electrical = 'electrical';
    case Plumbing = 'plumbing';
    case Environmental = 'environmental';
    case HealthAndSafety = 'health_and_safety';
    case Commercial = 'commercial';

    public function label(): string
    {
        return match ($this) {
            self::HealthAndSafety => 'Health and safety',
            default => ucfirst($this->value),
        };
    }
}
