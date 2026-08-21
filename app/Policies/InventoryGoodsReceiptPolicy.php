<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryGoodsReceipt;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryGoodsReceiptPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.stock.view');
    }

    public function view(User $user, InventoryGoodsReceipt $receipt): bool
    {
        return $this->belongsToSameTenant($user, $receipt->tenant_id) && $this->canAccessBranch($user, $receipt->branch_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.stock.receive');
    }
}
