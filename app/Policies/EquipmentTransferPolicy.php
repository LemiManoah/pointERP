<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentTransfer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentTransferPolicy
{
    use ChecksTenantAccess;

    public function view(User $user, EquipmentTransfer $transfer): bool
    {
        return $this->belongsToSameTenant($user, $transfer->tenant_id)
            && ($this->canAccessBranch($user, $transfer->source_branch_id) || $this->canAccessBranch($user, $transfer->destination_branch_id))
            && $user->can('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('equipment.transfers.request');
    }

    public function approve(User $user, EquipmentTransfer $transfer): bool
    {
        return $this->view($user, $transfer)
            && $this->canAccessBranch($user, $transfer->source_branch_id)
            && $transfer->status === EquipmentTransfer::STATUS_REQUESTED
            && $user->can('equipment.transfers.approve');
    }

    public function dispatch(User $user, EquipmentTransfer $transfer): bool
    {
        return $this->view($user, $transfer)
            && $this->canAccessBranch($user, $transfer->source_branch_id)
            && $transfer->status === EquipmentTransfer::STATUS_APPROVED
            && $user->can('equipment.transfers.dispatch');
    }

    public function receive(User $user, EquipmentTransfer $transfer): bool
    {
        return $this->view($user, $transfer)
            && $this->canAccessBranch($user, $transfer->destination_branch_id)
            && $transfer->status === EquipmentTransfer::STATUS_DISPATCHED
            && $user->can('equipment.transfers.receive');
    }
}
