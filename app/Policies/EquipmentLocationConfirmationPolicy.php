<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentLocationConfirmation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentLocationConfirmationPolicy
{
    use ChecksTenantAccess;

    public function create(User $user): bool
    {
        return $user->can('equipment.locations.confirm');
    }

    public function view(User $user, EquipmentLocationConfirmation $confirmation): bool
    {
        return $this->belongsToSameTenant($user, $confirmation->tenant_id)
            && $this->canAccessBranch($user, $confirmation->branch_id)
            && $user->can('equipment.view');
    }
}
