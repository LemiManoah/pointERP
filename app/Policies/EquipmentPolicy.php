<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool { return $user->can('equipment.view'); }
    public function view(User $user, Equipment $equipment): bool
    {
        return $this->belongsToSameTenant($user, $equipment->tenant_id)
            && $this->canAccessBranch($user, $equipment->branch_id)
            && $this->viewAny($user);
    }
    public function create(User $user): bool { return $user->can('equipment.create'); }
    public function update(User $user, Equipment $equipment): bool { return $this->view($user, $equipment) && $user->can('equipment.update'); }
    public function delete(User $user, Equipment $equipment): bool { return $this->view($user, $equipment) && $user->can('equipment.retire'); }
    public function viewCosts(User $user, Equipment $equipment): bool { return $this->view($user, $equipment) && $user->can('equipment.costs.view'); }
}
