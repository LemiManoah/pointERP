<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryApprovalStatus;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReviewInventoryTransfer
{
    public function __construct(private TransferInventoryStock $transferStock, private AuditLogger $auditLogger) {}

    public function approve(InventoryTransfer $transfer, User $actor): InventoryTransfer
    {
        return DB::transaction(function () use ($actor, $transfer): InventoryTransfer {
            $transfer = InventoryTransfer::query()->lockForUpdate()->with(['sourceStore', 'destinationStore', 'lines'])->findOrFail($transfer->id);
            if ($transfer->status !== InventoryApprovalStatus::PendingApproval) {
                throw ValidationException::withMessages(['transfer' => 'Only a pending transfer can be approved.']);
            }

            foreach ($transfer->lines as $line) {
                /** @var InventoryTransferLine $line */
                $item = InventoryItem::query()->findOrFail($line->inventory_item_id);
                $this->transferStock->handle($transfer->sourceStore, $transfer->destinationStore, $item, [
                    'original_quantity' => $line->quantity, 'original_unit_id' => $line->unit_of_measure_id,
                    'conversion_multiplier' => $line->conversion_multiplier, 'inventory_batch_id' => $line->inventory_batch_id,
                    'source_type' => InventoryTransfer::class, 'source_id' => $transfer->id,
                    'source_key' => 'inventory-transfer:'.$transfer->id.':'.$line->id, 'reason' => $transfer->reason,
                ], $actor);
            }

            $transfer->forceFill(['status' => InventoryApprovalStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->auditLogger->record('inventory.transfer.approved', $transfer, $actor, ['status' => InventoryApprovalStatus::PendingApproval->value], $transfer->only(['status', 'approved_by', 'approved_at']), $transfer->reason, $transfer->sourceStore->branch);

            return $transfer->refresh();
        });
    }

    public function reject(InventoryTransfer $transfer, string $reason, User $actor): InventoryTransfer
    {
        return DB::transaction(function () use ($actor, $reason, $transfer): InventoryTransfer {
            $transfer = InventoryTransfer::query()->lockForUpdate()->with('sourceStore.branch')->findOrFail($transfer->id);
            if ($transfer->status !== InventoryApprovalStatus::PendingApproval) {
                throw ValidationException::withMessages(['transfer' => 'Only a pending transfer can be rejected.']);
            }

            $transfer->forceFill(['status' => InventoryApprovalStatus::Rejected, 'decision_reason' => $reason, 'rejected_by' => $actor->id, 'rejected_at' => now()])->save();
            $this->auditLogger->record('inventory.transfer.rejected', $transfer, $actor, ['status' => InventoryApprovalStatus::PendingApproval->value], $transfer->only(['status', 'decision_reason']), $reason, $transfer->sourceStore->branch);

            return $transfer->refresh();
        });
    }
}
