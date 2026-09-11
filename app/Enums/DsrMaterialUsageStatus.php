<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrMaterialUsageStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case External = 'external';
    case NeedsAttention = 'needs_attention';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::Posted => 'Stock deducted',
            self::External => 'Outside inventory',
            self::NeedsAttention => 'Needs attention',
        };
    }
}
