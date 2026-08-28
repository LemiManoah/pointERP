<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTrackingType;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\InventoryDirectReceipt;
use App\Models\InventoryDirectReceiptLine;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryQuantityConverter;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AddInventoryStock
{
    public function __construct(
        private TenantContext $tenantContext,
        private InventoryQuantityConverter $converter,
        private PostInventoryStockMovement $postMovement,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $store, array $data, User $actor): InventoryDirectReceipt
    {
        return DB::transaction(function () use ($actor, $data, $store): InventoryDirectReceipt {
            $existing = InventoryDirectReceipt::query()->where('receipt_key', $data['receipt_key'])->first();
            if ($existing instanceof InventoryDirectReceipt) {
                return $existing;
            }

            $company = isset($data['source_company_id'])
                ? Customer::query()->where('status', 'active')->find($data['source_company_id'])
                : null;
            if ($company instanceof Customer && $company->branch_id !== null && $company->branch_id !== $store->branch_id) {
                throw ValidationException::withMessages(['source_company_id' => 'Select a company available to the destination branch.']);
            }

            $receipt = InventoryDirectReceipt::query()->create([
                'tenant_id' => $this->tenantContext->id(),
                'branch_id' => $store->branch_id,
                'inventory_store_id' => $store->id,
                'source_company_id' => $company?->id,
                'receipt_key' => $data['receipt_key'],
                'reference' => $this->reference(),
                'source_reference' => $data['source_reference'] ?? null,
                'received_on' => $data['received_on'],
                'reason' => $data['reason'],
                'received_by' => $actor->id,
            ]);

            /** @var list<array<string, mixed>> $lines */
            $lines = $data['lines'];
            foreach ($lines as $index => $line) {
                $item = InventoryItem::query()->where('is_active', true)->findOrFail($line['inventory_item_id']);
                $unit = UnitOfMeasure::query()->where('is_active', true)->findOrFail($line['unit_of_measure_id']);
                $multiplier = $this->converter->multiplier($item, $unit->id);
                $batch = $this->resolveBatch($item, $line, $actor);
                $movement = $this->postMovement->handle($store, $item, [
                    'movement_type' => InventoryMovementType::Receipt->value,
                    'original_quantity' => $line['quantity'],
                    'original_unit_id' => $unit->id,
                    'conversion_multiplier' => (string) $multiplier,
                    'inventory_batch_id' => $batch?->id,
                    'source_type' => InventoryDirectReceipt::class,
                    'source_id' => $receipt->id,
                    'source_key' => 'direct-receipt:'.$receipt->id.':'.$index,
                    'reason' => 'Added stock under '.$receipt->reference.': '.$receipt->reason->label(),
                ], $actor);

                InventoryDirectReceiptLine::query()->create([
                    'tenant_id' => $receipt->tenant_id,
                    'inventory_direct_receipt_id' => $receipt->id,
                    'inventory_item_id' => $item->id,
                    'unit_of_measure_id' => $unit->id,
                    'inventory_batch_id' => $batch?->id,
                    'inventory_stock_movement_id' => $movement->id,
                    'item_code_snapshot' => $item->code,
                    'item_name_snapshot' => $item->name,
                    'unit_symbol_snapshot' => $unit->symbol,
                    'quantity' => $movement->original_quantity,
                    'conversion_multiplier' => $movement->conversion_multiplier,
                    'stock_quantity' => $movement->quantity,
                    'batch_number' => $batch?->batch_number,
                    'manufactured_on' => $batch?->manufactured_on,
                    'expires_on' => $batch?->expires_on,
                ]);
            }

            $this->auditLogger->record('inventory.direct_receipt.created', $receipt, $actor, [], $receipt->load('lines')->toArray(), $receipt->reason->label(), $store->branch);

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

    private function reference(): string
    {
        do {
            $reference = 'SR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (InventoryDirectReceipt::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
