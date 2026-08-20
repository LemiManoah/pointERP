<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class UnitOfMeasurePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.items.view') || $user->can('inventory.items.manage');
    }

    public function view(User $user, UnitOfMeasure $unit): bool
    {
        return ($unit->tenant_id === null || $this->belongsToSameTenant($user, $unit->tenant_id)) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.items.manage');
    }

    public function update(User $user, UnitOfMeasure $unit): bool
    {
        return $unit->tenant_id !== null && $this->view($user, $unit) && $this->create($user);
    }

    public function delete(User $user, UnitOfMeasure $unit): bool
    {
        return $this->update($user, $unit);
    }
}
