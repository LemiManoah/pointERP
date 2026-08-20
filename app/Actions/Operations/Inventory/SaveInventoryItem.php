<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryTrackingType;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

final readonly class SaveInventoryItem
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?InventoryItem $item = null): InventoryItem
    {
        $canViewCosts = $actor->can('inventory.items.view-costs');
        $defaultUnitCost = $data['default_unit_cost'] ?? null;
        $defaultSellingPrice = $data['default_selling_price'] ?? null;
        $attributes = [
            'tenant_id' => $this->tenantContext->id(), 'inventory_category_id' => $data['inventory_category_id'], 'stock_unit_id' => $data['stock_unit_id'], 'preferred_supplier_id' => $data['preferred_supplier_id'] ?? null,
            'code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'material_class' => $data['material_class'], 'reorder_level' => $data['reorder_level'], 'reorder_quantity' => $data['reorder_quantity'] ?? null,
            'tracking_type' => $data['tracking_type'], 'batch_number' => $data['tracking_type'] === InventoryTrackingType::Batch->value ? $data['batch_number'] : null, 'is_expires' => $data['tracking_type'] === InventoryTrackingType::Batch->value || $data['is_expires'], 'is_for_sale' => $data['is_for_sale'],
            'default_unit_cost' => $canViewCosts ? $defaultUnitCost : ($item?->default_unit_cost), 'default_selling_price' => $canViewCosts ? $defaultSellingPrice : ($item?->default_selling_price), 'is_active' => $data['is_active'], 'updated_by' => $actor->id,
        ];
        $old = $item?->only(array_keys($attributes)) ?? [];
        if ($item instanceof InventoryItem) {
            $item->update($attributes);
            $event = 'inventory.item.updated';
        } else {
            $item = InventoryItem::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.item.created';
        }
        $this->auditLogger->record($event, $item, $actor, $old, $item->fresh()?->toArray() ?? []);

        return $item;
    }
}
