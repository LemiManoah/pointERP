<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryApprovalStatus;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryQuantityConverter;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferInventoryItems
{
    public function __construct(private TenantContext $tenantContext, private InventoryQuantityConverter $converter, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $source, InventoryStore $destination, array $data, User $actor): InventoryTransfer
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $data['lines'];

        return DB::transaction(function () use ($actor, $data, $destination, $lines, $source): InventoryTransfer {
            $existing = InventoryTransfer::query()->where('request_key', $data['transfer_key'])->first();
            if ($existing instanceof InventoryTransfer) {
                return $existing;
            }

            $transfer = InventoryTransfer::query()->create([
                'tenant_id' => $this->tenantContext->id(), 'branch_id' => $source->branch_id,
                'source_store_id' => $source->id, 'destination_store_id' => $destination->id,
                'reference' => 'TRF-'.now()->format('Ymd-His').'-'.mb_strtoupper(str()->random(4)),
                'request_key' => $data['transfer_key'],
                'status' => InventoryApprovalStatus::PendingApproval, 'reason' => $data['reason'],
                'requested_by' => $actor->id, 'requested_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                $item = InventoryItem::query()->where('is_active', true)->findOrFail($line['inventory_item_id']);
                $enabledStoreCount = InventoryStoreItem::query()->where('inventory_item_id', $item->id)->where('is_active', true)->whereIn('inventory_store_id', [$source->id, $destination->id])->count();
                if ($enabledStoreCount !== 2) {
                    throw ValidationException::withMessages([sprintf('lines.%d.inventory_item_id', $index) => 'The item must be enabled in both stores before it can be transferred.']);
                }

                $multiplier = $this->converter->multiplier($item, (string) $line['unit_of_measure_id']);
                $quantity = BigDecimal::of((string) $line['quantity']);
                InventoryTransferLine::query()->create([
                    'tenant_id' => $transfer->tenant_id, 'inventory_transfer_id' => $transfer->id,
                    'inventory_item_id' => $item->id, 'unit_of_measure_id' => $line['unit_of_measure_id'],
                    'inventory_batch_id' => $line['inventory_batch_id'] ?? null, 'quantity' => (string) $quantity->toScale(4),
                    'conversion_multiplier' => (string) $multiplier->toScale(10), 'stock_quantity' => (string) $quantity->multipliedBy($multiplier)->toScale(4),
                    'item_code_snapshot' => $item->code, 'item_name_snapshot' => $item->name,
                    'unit_symbol_snapshot' => $item->stockUnit->symbol ?? $item->stockUnit->name, 'sort_order' => $index,
                ]);
            }

            $this->auditLogger->record('inventory.transfer.requested', $transfer, $actor, [], $transfer->load('lines')->toArray(), (string) $data['reason'], $source->branch);

            return $transfer->refresh();
        });
    }
}
