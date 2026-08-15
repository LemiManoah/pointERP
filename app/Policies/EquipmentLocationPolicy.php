<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentLocation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentLocationPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('equipment.view')) {
            return true;
        }

        return $user->can('equipment.locations.manage');
    }

    public function view(User $user, EquipmentLocation $location): bool
    {
        return $this->belongsToSameTenant($user, $location->tenant_id) && $this->canAccessBranch($user, $location->branch_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('equipment.locations.manage');
    }

    public function update(User $user, EquipmentLocation $location): bool
    {
        return $this->view($user, $location) && $this->create($user);
    }

    public function delete(User $user, EquipmentLocation $location): bool
    {
        return $this->update($user, $location);
    }
}
