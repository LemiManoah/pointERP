<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MaterialRequisitionStatus;
use App\Models\MaterialRequisition;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class MaterialRequisitionPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.requisitions.view');
    }

    public function view(User $user, MaterialRequisition $requisition): bool
    {
        return $this->belongsToSameTenant($user, $requisition->tenant_id)
            && $this->canAccessBranch($user, $requisition->branch_id)
            && $user->can('inventory.requisitions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.requisitions.create');
    }

    public function update(User $user, MaterialRequisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && $requisition->isEditable()
            && $user->can('inventory.requisitions.create');
    }

    public function submit(User $user, MaterialRequisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && $requisition->isEditable()
            && $user->can('inventory.requisitions.submit');
    }

    public function approve(User $user, MaterialRequisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && $requisition->status === MaterialRequisitionStatus::Submitted
            && $user->can('inventory.requisitions.approve');
    }

    public function issue(User $user, MaterialRequisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && in_array($requisition->status, [MaterialRequisitionStatus::Approved, MaterialRequisitionStatus::PartiallyIssued], true)
            && $user->can('inventory.requisitions.issue');
    }

    public function returnStock(User $user, MaterialRequisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && in_array($requisition->status, [MaterialRequisitionStatus::PartiallyIssued, MaterialRequisitionStatus::Fulfilled], true)
            && $user->can('inventory.requisitions.return');
    }

    public function cancel(User $user, MaterialRequisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && ! in_array($requisition->status, [MaterialRequisitionStatus::Fulfilled, MaterialRequisitionStatus::Rejected, MaterialRequisitionStatus::Cancelled], true)
            && $user->can('inventory.requisitions.cancel');
    }
}
