<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentFuelTransaction;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentFuelTransactionPolicy
{
    use ChecksTenantAccess;

    public function view(User $user, EquipmentFuelTransaction $transaction): bool
    {
        return $this->belongsToSameTenant($user, $transaction->tenant_id)
            && $this->canAccessBranch($user, $transaction->branch_id)
            && $user->can('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('equipment.fuel.create');
    }

    public function approve(User $user, EquipmentFuelTransaction $transaction): bool
    {
        return $this->view($user, $transaction)
            && $transaction->status === EquipmentFuelTransaction::STATUS_SUBMITTED
            && $user->can('equipment.fuel.approve');
    }

    public function reverse(User $user, EquipmentFuelTransaction $transaction): bool
    {
        return $this->view($user, $transaction)
            && $transaction->status === EquipmentFuelTransaction::STATUS_POSTED
            && $user->can('equipment.fuel.reverse');
    }

    public function viewCosts(User $user, EquipmentFuelTransaction $transaction): bool
    {
        return $this->view($user, $transaction) && $user->can('equipment.costs.view');
    }
}
