<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InventoryApprovalStatus;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryTransferPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.transfers.view');
    }

    public function view(User $user, InventoryTransfer $transfer): bool
    {
        return $this->belongsToSameTenant($user, $transfer->tenant_id) && $this->canAccessBranch($user, $transfer->sourceStore->branch_id) && $this->canAccessBranch($user, $transfer->destinationStore->branch_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.transfers.create');
    }

    public function approve(User $user, InventoryTransfer $transfer): bool
    {
        return $this->view($user, $transfer) && $transfer->status === InventoryApprovalStatus::PendingApproval && $user->can('inventory.transfers.approve');
    }

    public function reject(User $user, InventoryTransfer $transfer): bool
    {
        return $this->view($user, $transfer) && $transfer->status === InventoryApprovalStatus::PendingApproval && $user->can('inventory.transfers.reject');
    }
}
