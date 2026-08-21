<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryPaymentStatus;
use App\Enums\InventoryTrackingType;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryGoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ReceiveInventoryStock
{
    public function __construct(private TenantContext $tenantContext, private BranchContext $branchContext, private PostInventoryStockMovement $postMovement, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): InventoryGoodsReceipt
    {
        return DB::transaction(function () use ($actor, $data): InventoryGoodsReceipt {
            $branch = $this->resolveBranch($data, $actor);
            $store = InventoryStore::query()->whereKey($data['inventory_store_id'])->where('branch_id', $branch->id)->where('is_active', true)->firstOrFail();
            /** @var list<array<string, mixed>> $lines */
            $lines = $data['lines'];
            $canViewCosts = $actor->can('inventory.receipts.view-costs');
            $total = collect($lines)->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus(BigDecimal::of((string) $line['quantity'])->multipliedBy($canViewCosts ? (string) ($line['unit_cost'] ?? 0) : '0')), BigDecimal::zero());
            $paid = BigDecimal::of($canViewCosts ? (string) ($data['amount_paid'] ?? 0) : '0');
            if ($paid->isGreaterThan($total)) {
                throw ValidationException::withMessages(['amount_paid' => 'Amount paid cannot exceed the receipt total.']);
            }

            $status = $paid->isZero() ? InventoryPaymentStatus::Unpaid : ($paid->isLessThan($total) ? InventoryPaymentStatus::PartiallyPaid : InventoryPaymentStatus::Paid);
            $receipt = InventoryGoodsReceipt::query()->create([
                'tenant_id' => $this->tenantContext->id(), 'branch_id' => $branch->id, 'inventory_store_id' => $store->id,
                'supplier_id' => $data['supplier_id'], 'reference' => 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'supplier_reference' => $data['supplier_reference'] ?? null, 'received_on' => $data['received_on'],
                'currency_code' => $branch->default_currency_code, 'total_amount' => (string) $total->toScale(4, RoundingMode::HalfUp),
                'amount_paid' => (string) $paid->toScale(4, RoundingMode::HalfUp), 'payment_status' => $status,
                'notes' => $data['notes'] ?? null, 'received_by' => $actor->id,
            ]);
            foreach ($lines as $index => $line) {
                $item = InventoryItem::query()->whereKey($line['inventory_item_id'])->where('is_active', true)->firstOrFail();
                InventoryStoreItem::query()->firstOrCreate(['tenant_id' => $receipt->tenant_id, 'inventory_store_id' => $store->id, 'inventory_item_id' => $item->id], ['is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
                $batch = $this->resolveBatch($item, $line, $actor);
                $movement = $this->postMovement->handle($store, $item, [
                    'movement_type' => InventoryMovementType::Receipt->value, 'original_quantity' => $line['quantity'],
                    'original_unit_id' => $line['unit_of_measure_id'], 'inventory_batch_id' => $batch?->id,
                    'source_type' => InventoryGoodsReceipt::class, 'source_id' => $receipt->id,
                    'source_key' => 'goods-receipt:'.$receipt->id.':'.$index, 'reason' => 'Direct stock receipt '.$receipt->reference,
                ], $actor);
                InventoryGoodsReceiptLine::query()->create([
                    'tenant_id' => $receipt->tenant_id, 'inventory_goods_receipt_id' => $receipt->id, 'inventory_item_id' => $item->id,
                    'inventory_batch_id' => $batch?->id, 'inventory_stock_movement_id' => $movement->id, 'quantity' => $line['quantity'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'], 'stock_quantity' => $movement->quantity,
                    'unit_cost' => $canViewCosts ? ($line['unit_cost'] ?? 0) : 0, 'line_total' => (string) BigDecimal::of((string) $line['quantity'])->multipliedBy($canViewCosts ? (string) ($line['unit_cost'] ?? 0) : '0')->toScale(4, RoundingMode::HalfUp),
                    'batch_number' => $batch?->batch_number, 'manufactured_on' => $line['manufactured_on'] ?? null, 'expires_on' => $line['expires_on'] ?? null,
                ]);
            }

            $this->auditLogger->record('inventory.goods_receipt.recorded', $receipt, $actor, [], $receipt->toArray(), $receipt->notes, $branch);

            return $receipt;
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveBranch(array $data, User $actor): Branch
    {
        $default = $this->branchContext->current($actor) ?? $this->branchContext->operationalDefault($actor);
        $requested = $data['branch_id'] ?? null;
        $canChange = $actor->can('inventory.stock.change-branch') && $this->branchContext->accessibleBranches($actor)->count() > 1;
        $branch = $canChange && is_string($requested) ? $this->branchContext->accessibleBranches($actor)->firstWhere('id', $requested) : $default;
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages(['branch_id' => 'Select an operational branch.']);
        }

        return $branch;
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
