<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryApprovalStatus;
use App\Models\InventoryItem;
use App\Models\InventoryReconciliation;
use App\Models\InventoryReconciliationLine;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReconcileInventoryStockCount
{
    public function __construct(private TenantContext $tenantContext, private InventoryStockBalance $balances, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(InventoryStore $store, array $data, User $actor): InventoryReconciliation
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $data['lines'];

        return DB::transaction(function () use ($actor, $data, $lines, $store): InventoryReconciliation {
            $existing = InventoryReconciliation::query()->where('request_key', $data['count_key'])->first();
            if ($existing instanceof InventoryReconciliation) {
                return $existing;
            }

            $entered = collect($lines)->filter(fn (array $line): bool => ($line['counted_quantity'] ?? null) !== null && $line['counted_quantity'] !== '')->values();
            if ($entered->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Enter at least one physical quantity.']);
            }

            $reconciliation = InventoryReconciliation::query()->create([
                'tenant_id' => $this->tenantContext->id(), 'branch_id' => $store->branch_id, 'inventory_store_id' => $store->id,
                'reference' => 'REC-'.now()->format('Ymd-His').'-'.mb_strtoupper(str()->random(4)),
                'request_key' => $data['count_key'],
                'status' => InventoryApprovalStatus::PendingApproval, 'reason' => $data['reason'],
                'requested_by' => $actor->id, 'requested_at' => now(),
            ]);

            foreach ($entered as $index => $line) {
                $item = InventoryItem::query()->findOrFail($line['inventory_item_id']);
                $batchId = $line['inventory_batch_id'] ?? null;
                $system = BigDecimal::of(is_string($batchId)
                    ? $this->balances->forBatch($store, $item, $batchId)
                    : $this->balances->for($store, $item)['on_hand']);
                $counted = BigDecimal::of((string) $line['counted_quantity']);
                InventoryReconciliationLine::query()->create([
                    'tenant_id' => $reconciliation->tenant_id, 'inventory_reconciliation_id' => $reconciliation->id,
                    'inventory_item_id' => $item->id, 'inventory_batch_id' => $batchId,
                    'system_quantity' => (string) $system->toScale(4), 'counted_quantity' => (string) $counted->toScale(4),
                    'variance_quantity' => (string) $counted->minus($system)->toScale(4),
                    'item_code_snapshot' => $item->code, 'item_name_snapshot' => $item->name,
                    'unit_symbol_snapshot' => $item->stockUnit->symbol ?? $item->stockUnit->name, 'sort_order' => $index,
                ]);
            }

            $this->auditLogger->record('inventory.reconciliation.requested', $reconciliation, $actor, [], $reconciliation->load('lines')->toArray(), (string) $data['reason'], $store->branch);

            return $reconciliation->refresh();
        });
    }
}
