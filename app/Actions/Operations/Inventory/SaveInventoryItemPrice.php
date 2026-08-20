<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemPrice;
use App\Models\InventoryPriceTier;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\TenantContext;

final readonly class SaveInventoryItemPrice
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext, private BranchContext $branchContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, InventoryItem $item, User $actor, ?InventoryItemPrice $price = null): InventoryItemPrice
    {
        $tenantId = $this->tenantContext->id();
        $tier = InventoryPriceTier::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => $data['tier_code']],
            ['name' => $data['tier_name'], 'is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id],
        );
        $tier->update(['name' => $data['tier_name'], 'is_active' => true, 'updated_by' => $actor->id]);

        $branchId = $data['branch_id'] ?? $this->branchContext->current($actor)?->id;
        $price ??= InventoryItemPrice::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_price_tier_id', $tier->id)
            ->where('branch_id', $branchId)
            ->where('unit_of_measure_id', $data['unit_of_measure_id'])
            ->first();
        $attributes = [
            'tenant_id' => $tenantId,
            'inventory_item_id' => $item->id,
            'inventory_price_tier_id' => $tier->id,
            'branch_id' => $branchId,
            'unit_of_measure_id' => $data['unit_of_measure_id'],
            'amount' => $data['amount'],
            'minimum_quantity' => $data['minimum_quantity'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
        $old = $price?->only(array_keys($attributes)) ?? [];
        if ($price instanceof InventoryItemPrice) {
            $price->update($attributes);
            $event = 'inventory.item_price.updated';
        } else {
            $price = InventoryItemPrice::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'inventory.item_price.created';
        }

        $this->auditLogger->record($event, $price, $actor, $old, $price->fresh()?->toArray() ?? []);

        return $price;
    }
}
