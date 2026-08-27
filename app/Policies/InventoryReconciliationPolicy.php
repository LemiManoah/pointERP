<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InventoryApprovalStatus;
use App\Models\InventoryReconciliation;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class InventoryReconciliationPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.reconciliations.view');
    }

    public function view(User $user, InventoryReconciliation $reconciliation): bool
    {
        return $this->belongsToSameTenant($user, $reconciliation->tenant_id) && $this->canAccessBranch($user, $reconciliation->branch_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.reconciliations.create');
    }

    public function approve(User $user, InventoryReconciliation $reconciliation): bool
    {
        return $this->view($user, $reconciliation) && $reconciliation->status === InventoryApprovalStatus::PendingApproval && $user->can('inventory.reconciliations.approve');
    }

    public function reject(User $user, InventoryReconciliation $reconciliation): bool
    {
        return $this->view($user, $reconciliation) && $reconciliation->status === InventoryApprovalStatus::PendingApproval && $user->can('inventory.reconciliations.reject');
    }
}
