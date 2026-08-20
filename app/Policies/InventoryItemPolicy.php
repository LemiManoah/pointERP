<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryItemPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('inventory.items.view')) {
            return true;
        }

        return $user->can('inventory.items.manage');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $this->belongsToSameTenant($user, $item->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $this->view($user, $item) && $this->create($user);
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $this->update($user, $item);
    }

    public function forceDelete(User $user, InventoryItem $item): bool
    {
        return $this->view($user, $item) && $user->can('inventory.items.delete');
    }

    public function viewCosts(User $user): bool
    {
        return $user->can('inventory.items.view-costs');
    }
}
