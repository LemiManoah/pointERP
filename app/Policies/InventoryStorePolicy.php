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
            && $this->viewAny($user);
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
}
