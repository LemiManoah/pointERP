<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentMeterReadingPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('equipment.view');
    }

    public function view(User $user, EquipmentMeterReading $reading): bool
    {
        return $this->belongsToSameTenant($user, $reading->tenant_id)
            && $this->canAccessBranch($user, $reading->branch_id)
            && $this->viewAny($user);
    }

    public function create(User $user, Equipment $equipment): bool
    {
        return $user->can('equipment.readings.create')
            && $this->belongsToSameTenant($user, $equipment->tenant_id)
            && $this->canAccessBranch($user, $equipment->branch_id);
    }

    public function correct(User $user, EquipmentMeterReading $reading): bool
    {
        return $this->view($user, $reading) && $user->can('equipment.readings.correct');
    }

    public function approveCorrection(User $user, EquipmentMeterReading $reading): bool
    {
        return $this->view($user, $reading) && $user->can('equipment.readings.approve-correction');
    }
}
