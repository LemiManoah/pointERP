<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTrackingType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryGoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ReceiveInventoryStock
{
    public function __construct(
        private TenantContext $tenantContext,
        private PostInventoryStockMovement $postMovement,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): InventoryGoodsReceipt
    {
        return DB::transaction(function () use ($actor, $data): InventoryGoodsReceipt {
            $purchaseOrder = PurchaseOrder::query()
                ->where('tenant_id', $this->tenantContext->id())
                ->lockForUpdate()
                ->findOrFail($data['purchase_order_id']);

            if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Only an approved purchase order can be received.']);
            }

            $branch = Branch::query()->findOrFail($purchaseOrder->branch_id);
            $store = InventoryStore::query()
                ->whereKey($purchaseOrder->inventory_store_id)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->firstOrFail();

            $receipt = InventoryGoodsReceipt::query()->create([
                'tenant_id' => $purchaseOrder->tenant_id,
                'branch_id' => $branch->id,
                'inventory_store_id' => $store->id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'reference' => 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'received_on' => $data['received_on'],
                'currency_code' => $purchaseOrder->currency_code,
                'total_amount' => '0.0000',
                'inspection_status' => 'accepted',
                'notes' => $data['notes'] ?? null,
                'received_by' => $actor->id,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);

            /** @var list<array<string, mixed>> $lines */
            $lines = $data['lines'];
            $acceptedTotal = BigDecimal::zero();
            $rejectedTotal = BigDecimal::zero();
            $receiptTotal = BigDecimal::zero();

            foreach ($lines as $index => $line) {
                $purchaseOrderLine = PurchaseOrderLine::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->find($line['purchase_order_line_id']);

                if (! $purchaseOrderLine instanceof PurchaseOrderLine) {
                    throw ValidationException::withMessages([sprintf('lines.%d.purchase_order_line_id', $index) => 'Select a line belonging to the chosen purchase order.']);
                }

                $item = InventoryItem::query()
                    ->whereKey($purchaseOrderLine->inventory_item_id)
                    ->where('is_active', true)
                    ->firstOrFail();
                $delivered = BigDecimal::of((string) $line['quantity']);
                $accepted = BigDecimal::of((string) $line['accepted_quantity']);
                $rejected = BigDecimal::of((string) $line['rejected_quantity']);

                if ($accepted->isGreaterThan($purchaseOrderLine->outstandingQuantity())) {
                    throw ValidationException::withMessages([sprintf('lines.%d.accepted_quantity', $index) => 'Accepted quantity exceeds the outstanding purchase-order quantity.']);
                }

                InventoryStoreItem::query()->firstOrCreate(
                    ['tenant_id' => $receipt->tenant_id, 'inventory_store_id' => $store->id, 'inventory_item_id' => $item->id],
                    ['is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id],
                );

                $batch = $accepted->isZero() ? null : $this->resolveBatch($item, $line, $actor);
                $movement = $accepted->isZero() ? null : $this->postMovement->handle($store, $item, [
                    'movement_type' => InventoryMovementType::Receipt->value,
                    'original_quantity' => (string) $accepted,
                    'original_unit_id' => $purchaseOrderLine->unit_of_measure_id,
                    'inventory_batch_id' => $batch?->id,
                    'source_type' => InventoryGoodsReceipt::class,
                    'source_id' => $receipt->id,
                    'source_key' => 'goods-receipt:'.$receipt->id.':'.$index,
                    'reason' => 'Purchase order receipt '.$receipt->reference,
                ], $actor);

                $lineTotal = $accepted->multipliedBy((string) $purchaseOrderLine->unit_price)->toScale(4, RoundingMode::HalfUp);
                InventoryGoodsReceiptLine::query()->create([
                    'tenant_id' => $receipt->tenant_id,
                    'inventory_goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $purchaseOrderLine->id,
                    'inventory_item_id' => $item->id,
                    'inventory_batch_id' => $batch?->id,
                    'inventory_stock_movement_id' => $movement?->id,
                    'quantity' => (string) $delivered,
                    'accepted_quantity' => (string) $accepted,
                    'rejected_quantity' => (string) $rejected,
                    'rejection_reason' => $line['rejection_reason'] ?? null,
                    'unit_of_measure_id' => $purchaseOrderLine->unit_of_measure_id,
                    'stock_quantity' => $movement === null ? '0.0000' : $movement->quantity,
                    'unit_cost' => $purchaseOrderLine->unit_price,
                    'line_total' => (string) $lineTotal,
                    'batch_number' => $batch?->batch_number,
                    'manufactured_on' => $line['manufactured_on'] ?? null,
                    'expires_on' => $line['expires_on'] ?? null,
                ]);

                $purchaseOrderLine->forceFill([
                    'accepted_quantity' => (string) BigDecimal::of((string) $purchaseOrderLine->accepted_quantity)->plus($accepted)->toScale(4),
                    'rejected_quantity' => (string) BigDecimal::of((string) $purchaseOrderLine->rejected_quantity)->plus($rejected)->toScale(4),
                ])->save();

                $acceptedTotal = $acceptedTotal->plus($accepted);
                $rejectedTotal = $rejectedTotal->plus($rejected);
                $receiptTotal = $receiptTotal->plus($lineTotal);
            }

            $inspectionStatus = $rejectedTotal->isZero() ? 'accepted' : ($acceptedTotal->isZero() ? 'rejected' : 'partially_rejected');
            $receipt->forceFill([
                'total_amount' => (string) $receiptTotal->toScale(4, RoundingMode::HalfUp),
                'inspection_status' => $inspectionStatus,
            ])->save();

            $complete = $purchaseOrder->lines()->get()->every(
                fn (PurchaseOrderLine $line): bool => ! BigDecimal::of($line->outstandingQuantity())->isPositive(),
            );
            $purchaseOrder->forceFill([
                'status' => $complete ? PurchaseOrderStatus::Received : ($acceptedTotal->isPositive() ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Approved),
                'updated_by' => $actor->id,
            ])->save();

            $this->auditLogger->record('inventory.goods_receipt.recorded', $receipt, $actor, [], $receipt->toArray(), $receipt->notes, $branch);

            return $receipt;
        });
    }

    /** @param array<string, mixed> $line */
    private function resolveBatch(InventoryItem $item, array $line, User $actor): ?InventoryBatch
    {
        if ($item->tracking_type !== InventoryTrackingType::Batch) {
            return null;
        }

        $number = mb_trim((string) ($line['batch_number'] ?? ''));
        if ($number === '') {
            throw ValidationException::withMessages(['lines' => 'A batch number is required for '.$item->name.'.']);
        }

        if ($item->is_expires && empty($line['expires_on'])) {
            throw ValidationException::withMessages(['lines' => 'An expiry date is required for '.$item->name.'.']);
        }

        return InventoryBatch::query()->firstOrCreate(
            ['tenant_id' => $item->tenant_id, 'inventory_item_id' => $item->id, 'batch_number' => $number],
            ['inventory_store_id' => null, 'manufactured_on' => $line['manufactured_on'] ?? null, 'expires_on' => $line['expires_on'] ?? null, 'status' => InventoryBatchStatus::Available, 'is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id],
        );
    }
}
