<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentConfidentiality: string
{
    case Normal = 'normal';
    case Restricted = 'restricted';
    case Confidential = 'confidential';
    case Commercial = 'commercial';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
