<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryStoreItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

final readonly class SaveInventoryStoreItem
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, InventoryItem $item, User $actor, ?InventoryStoreItem $setting = null): InventoryStoreItem
    {
        $setting ??= InventoryStoreItem::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_store_id', $data['inventory_store_id'])
            ->first();
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'inventory_item_id' => $item->id,
            'inventory_store_id' => $data['inventory_store_id'],
            'minimum_stock' => $data['minimum_stock'] ?? $item->minimum_stock,
            'reorder_quantity' => $data['reorder_quantity'] ?? $item->reorder_quantity,
            'storage_location' => $data['storage_location'] ?? null,
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
        $old = $setting?->only(array_keys($attributes)) ?? [];
        if ($setting instanceof InventoryStoreItem) {
            $setting->update($attributes);
            $event = 'inventory.store_item.updated';
        } else {
            $setting = InventoryStoreItem::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.store_item.created';
        }

        $this->auditLogger->record($event, $setting, $actor, $old, $setting->fresh()?->toArray() ?? []);

        return $setting;
    }
}
