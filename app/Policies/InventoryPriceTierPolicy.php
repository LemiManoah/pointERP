<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryPriceTier;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryPriceTierPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.items.view-costs');
    }

    public function view(User $user, InventoryPriceTier $tier): bool
    {
        return $this->belongsToSameTenant($user, $tier->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage') && $user->can('inventory.items.view-costs');
    }

    public function update(User $user, InventoryPriceTier $tier): bool
    {
        return $this->view($user, $tier) && $this->create($user);
    }

    public function forceDelete(User $user, InventoryPriceTier $tier): bool
    {
        return $this->view($user, $tier) && $user->can('inventory.items.delete');
    }
}
