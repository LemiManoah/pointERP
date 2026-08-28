<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryDirectReceipt;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryDirectReceiptPolicy
{
    use ChecksTenantAccess;

    public function view(User $user, InventoryDirectReceipt $receipt): bool
    {
        return $this->belongsToSameTenant($user, $receipt->tenant_id)
            && $this->canAccessBranch($user, $receipt->branch_id)
            && $user->can('inventory.stock.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.stock.add');
    }
}
