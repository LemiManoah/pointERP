<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryTrackingType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Validation\ValidationException;

final readonly class SaveInventoryBatch
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, InventoryItem $item, User $actor, ?InventoryBatch $batch = null): InventoryBatch
    {
        if ($item->tracking_type !== InventoryTrackingType::Batch) {
            throw ValidationException::withMessages(['batch_number' => 'Batches can only be recorded for a batch-tracked item.']);
        }

        $batch ??= InventoryBatch::query()
            ->where('inventory_item_id', $item->id)
            ->where('batch_number', $data['batch_number'])
            ->first();
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'inventory_item_id' => $item->id,
            'inventory_store_id' => $data['inventory_store_id'] ?? null,
            'batch_number' => $data['batch_number'],
            'manufactured_on' => $data['manufactured_on'] ?? null,
            'expires_on' => $data['expires_on'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
        $old = $batch?->only(array_keys($attributes)) ?? [];
        if ($batch instanceof InventoryBatch) {
            $batch->update($attributes);
            $event = 'inventory.batch.updated';
        } else {
            $batch = InventoryBatch::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.batch.created';
        }

        $this->auditLogger->record($event, $batch, $actor, $old, $batch->fresh()?->toArray() ?? []);

        return $batch;
    }
}
