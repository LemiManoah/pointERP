<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryCategoryPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('inventory.items.view')) {
            return true;
        }

        return $user->can('inventory.items.manage');
    }

    public function view(User $user, InventoryCategory $category): bool
    {
        return $this->belongsToSameTenant($user, $category->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage');
    }

    public function update(User $user, InventoryCategory $category): bool
    {
        return $this->view($user, $category) && $this->create($user);
    }

    public function delete(User $user, InventoryCategory $category): bool
    {
        return $this->update($user, $category);
    }

    public function forceDelete(User $user, InventoryCategory $category): bool
    {
        return $this->view($user, $category) && $user->can('inventory.items.delete');
    }
}
