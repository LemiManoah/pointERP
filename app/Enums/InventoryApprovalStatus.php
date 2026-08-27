<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryApprovalStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
