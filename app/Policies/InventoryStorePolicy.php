<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryStore;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryStorePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('inventory.stores.view')) {
            return true;
        }

        return $user->can('inventory.stores.manage');
    }

    public function view(User $user, InventoryStore $store): bool
    {
        return $this->belongsToSameTenant($user, $store->tenant_id)
            && $this->canAccessBranch($user, $store->branch_id)
            && $this->viewAny($user)
            && $this->canAccessSiteStore($user, $store);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.stores.manage');
    }

    public function update(User $user, InventoryStore $store): bool
    {
        return $this->view($user, $store) && $this->create($user);
    }

    public function delete(User $user, InventoryStore $store): bool
    {
        return $this->update($user, $store);
    }

    public function forceDelete(User $user, InventoryStore $store): bool
    {
        return $this->view($user, $store) && $user->can('inventory.stores.delete');
    }

    private function canAccessSiteStore(User $user, InventoryStore $store): bool
    {
        if ($store->site_id === null) {
            return true;
        }

        if ($user->can('branches.view-all') || $user->can('inventory.stores.manage') || $user->can('inventory.stock.issue') || $user->can('inventory.stock.receive') || $user->can('inventory.stock.transfer')) {
            return true;
        }

        return $store->site()->visibleTo($user)->exists();
    }
}
