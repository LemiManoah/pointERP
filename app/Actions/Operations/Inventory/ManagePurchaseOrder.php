<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ManagePurchaseOrder
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function submit(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Submitted, $actor, 'inventory.purchase_order.submitted');
    }

    public function review(PurchaseOrder $purchaseOrder, string $decision, ?string $reason, User $actor): PurchaseOrder
    {
        $status = match ($decision) {
            'approve' => PurchaseOrderStatus::Approved, 'return' => PurchaseOrderStatus::Returned, default => PurchaseOrderStatus::Rejected
        };

        return DB::transaction(function () use ($actor, $decision, $purchaseOrder, $reason, $status): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($purchaseOrder->status !== PurchaseOrderStatus::Submitted) {
                throw ValidationException::withMessages(['purchase_order' => 'Only a submitted purchase order can be reviewed.']);
            }

            $purchaseOrder->forceFill(['status' => $status, 'decision_reason' => $reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'approved_by' => $decision === 'approve' ? $actor->id : null, 'approved_at' => $decision === 'approve' ? now() : null, 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('inventory.purchase_order.'.$decision, $purchaseOrder, $actor, ['status' => PurchaseOrderStatus::Submitted->value], ['status' => $status->value], $reason);

            return $purchaseOrder->refresh();
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Cancelled, $actor, 'inventory.purchase_order.cancelled', $reason);
    }

    public function close(PurchaseOrder $purchaseOrder, string $reason, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Closed, $actor, 'inventory.purchase_order.closed', $reason);
    }

    private function transition(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $status, User $actor, string $event, ?string $reason = null): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $event, $purchaseOrder, $reason, $status): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            $oldStatus = $purchaseOrder->status;
            if ($status === PurchaseOrderStatus::Submitted && ! $purchaseOrder->isEditable()) {
                throw ValidationException::withMessages(['purchase_order' => 'Only a draft or returned order can be submitted.']);
            }

            $values = ['status' => $status, 'decision_reason' => $reason, 'updated_by' => $actor->id];
            if ($status === PurchaseOrderStatus::Submitted) {
                $values += ['submitted_by' => $actor->id, 'submitted_at' => now()];
            }

            if ($status === PurchaseOrderStatus::Cancelled) {
                $values += ['cancelled_by' => $actor->id, 'cancelled_at' => now()];
            }

            if ($status === PurchaseOrderStatus::Closed) {
                $values += ['closed_by' => $actor->id, 'closed_at' => now()];
            }

            if (in_array($status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Closed], true)) {
                $purchaseOrder->lines()->get()->each(function (PurchaseOrderLine $line): void {
                    $line->forceFill(['cancelled_quantity' => $line->outstandingQuantity()])->save();
                });
            }

            $purchaseOrder->forceFill($values)->save();
            $this->auditLogger->record($event, $purchaseOrder, $actor, ['status' => $oldStatus->value], ['status' => $status->value], $reason);

            return $purchaseOrder->refresh();
        });
    }
}
