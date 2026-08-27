<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryApprovalStatus;
use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryReconciliation;
use App\Models\InventoryReconciliationLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryStockBalance;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReviewInventoryReconciliation
{
    public function __construct(private InventoryStockBalance $balances, private PostInventoryStockMovement $postMovement, private AuditLogger $auditLogger) {}

    public function approve(InventoryReconciliation $reconciliation, User $actor): InventoryReconciliation
    {
        return DB::transaction(function () use ($actor, $reconciliation): InventoryReconciliation {
            $reconciliation = InventoryReconciliation::query()->lockForUpdate()->with(['store', 'lines'])->findOrFail($reconciliation->id);
            if ($reconciliation->status !== InventoryApprovalStatus::PendingApproval) {
                throw ValidationException::withMessages(['reconciliation' => 'Only a pending reconciliation can be approved.']);
            }

            foreach ($reconciliation->lines as $line) {
                /** @var InventoryReconciliationLine $line */
                $item = InventoryItem::query()->findOrFail($line->inventory_item_id);
                $batchId = $line->inventory_batch_id;
                $current = BigDecimal::of(is_string($batchId) ? $this->balances->forBatch($reconciliation->store, $item, $batchId) : $this->balances->for($reconciliation->store, $item)['on_hand']);
                if (! $current->isEqualTo((string) $line->system_quantity)) {
                    throw ValidationException::withMessages(['reconciliation' => $item->name.' changed after the physical count. Reject this reconciliation and create a new one.']);
                }

                $variance = BigDecimal::of((string) $line->variance_quantity);
                if ($variance->isZero()) {
                    continue;
                }

                $this->postMovement->handle($reconciliation->store, $item, [
                    'movement_type' => InventoryMovementType::Adjustment->value, 'adjustment_direction' => $variance->isNegative() ? 'decrease' : 'increase',
                    'original_quantity' => (string) $variance->abs(), 'original_unit_id' => $item->stock_unit_id,
                    'inventory_batch_id' => $batchId, 'source_type' => InventoryReconciliation::class,
                    'source_id' => $reconciliation->id, 'source_key' => 'inventory-reconciliation:'.$reconciliation->id.':'.$line->id,
                    'reason' => $reconciliation->reason,
                ], $actor);
            }

            $reconciliation->forceFill(['status' => InventoryApprovalStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->auditLogger->record('inventory.reconciliation.approved', $reconciliation, $actor, ['status' => InventoryApprovalStatus::PendingApproval->value], $reconciliation->only(['status', 'approved_by', 'approved_at']), $reconciliation->reason, $reconciliation->store->branch);

            return $reconciliation->refresh();
        });
    }

    public function reject(InventoryReconciliation $reconciliation, string $reason, User $actor): InventoryReconciliation
    {
        return DB::transaction(function () use ($actor, $reason, $reconciliation): InventoryReconciliation {
            $reconciliation = InventoryReconciliation::query()->lockForUpdate()->with('store.branch')->findOrFail($reconciliation->id);
            if ($reconciliation->status !== InventoryApprovalStatus::PendingApproval) {
                throw ValidationException::withMessages(['reconciliation' => 'Only a pending reconciliation can be rejected.']);
            }

            $reconciliation->forceFill(['status' => InventoryApprovalStatus::Rejected, 'decision_reason' => $reason, 'rejected_by' => $actor->id, 'rejected_at' => now()])->save();
            $this->auditLogger->record('inventory.reconciliation.rejected', $reconciliation, $actor, ['status' => InventoryApprovalStatus::PendingApproval->value], $reconciliation->only(['status', 'decision_reason']), $reason, $reconciliation->store->branch);

            return $reconciliation->refresh();
        });
    }
}
