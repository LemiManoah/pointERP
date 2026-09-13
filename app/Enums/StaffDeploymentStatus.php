<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffDeploymentStatus: string
{
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
