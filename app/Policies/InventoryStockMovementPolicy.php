<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InventoryMovementType;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryStockMovementPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.stock.view');
    }

    public function view(User $user, InventoryStockMovement $movement): bool
    {
        return $this->belongsToSameTenant($user, $movement->tenant_id) && $this->canAccessBranch($user, $movement->branch_id) && $this->viewAny($user);
    }

    public function post(User $user, InventoryStore $store, InventoryMovementType $type): bool
    {
        if (! $this->belongsToSameTenant($user, $store->tenant_id) || ! $this->canAccessBranch($user, $store->branch_id)) {
            return false;
        }

        return match ($type) {
            InventoryMovementType::Issue => $user->can('inventory.stock.issue'),
            InventoryMovementType::Return => $user->can('inventory.stock.return'),
            default => $user->can('inventory.stock.adjust'),
        };
    }

    public function reverse(User $user, InventoryStockMovement $movement): bool
    {
        return $this->view($user, $movement) && $user->can('inventory.stock.reverse');
    }
}
