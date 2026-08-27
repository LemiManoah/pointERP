<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class PurchaseOrderPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('inventory.purchase-orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->belongsToSameTenant($user, $purchaseOrder->tenant_id) && $this->canAccessBranch($user, $purchaseOrder->branch_id) && $user->can('inventory.purchase-orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.purchase-orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder) && $purchaseOrder->isEditable() && $user->can('inventory.purchase-orders.create');
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder) && $purchaseOrder->isEditable() && $user->can('inventory.purchase-orders.submit');
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder) && $purchaseOrder->status === PurchaseOrderStatus::Submitted && $user->can('inventory.purchase-orders.approve');
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder) && ! in_array($purchaseOrder->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Closed], true) && $user->can('inventory.purchase-orders.cancel');
    }

    public function close(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder) && in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true) && $user->can('inventory.purchase-orders.close');
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder)
            && in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)
            && $user->can('inventory.stock.receive');
    }

    public function viewCosts(User $user): bool
    {
        return $user->can('inventory.purchase-orders.view-costs');
    }
}
