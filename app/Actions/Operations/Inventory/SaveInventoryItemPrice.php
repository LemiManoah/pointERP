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
use Illuminate\Validation\ValidationException;

final readonly class SaveInventoryItemPrice
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext, private BranchContext $branchContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, InventoryItem $item, User $actor, ?InventoryItemPrice $price = null): InventoryItemPrice
    {
        $tenantId = $this->tenantContext->id();
        $tier = InventoryPriceTier::query()
            ->whereKey($price instanceof InventoryItemPrice ? $price->inventory_price_tier_id : $data['inventory_price_tier_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $defaultBranch = $this->branchContext->current($actor) ?? $this->branchContext->operationalDefault($actor);
        $requestedBranchId = $data['branch_id'] ?? null;
        $canChangeBranch = $actor->can('inventory.prices.change-branch') && $this->branchContext->accessibleBranches($actor)->count() > 1;
        $branchId = ($price instanceof InventoryItemPrice ? $price->branch_id : null) ?? ($canChangeBranch && is_string($requestedBranchId) && $requestedBranchId !== ''
            ? $requestedBranchId
            : $defaultBranch?->id);
        if (! is_string($branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Select a branch before recording an item price.']);
        }

        $price ??= InventoryItemPrice::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_price_tier_id', $tier->id)
            ->where('branch_id', $branchId)
            ->where('unit_of_measure_id', $item->stock_unit_id)
            ->first();
        $attributes = [
            'tenant_id' => $tenantId,
            'inventory_item_id' => $item->id,
            'inventory_price_tier_id' => $tier->id,
            'branch_id' => $branchId,
            'unit_of_measure_id' => $item->stock_unit_id,
            'amount' => $data['amount'],
            'minimum_quantity' => null,
            'effective_from' => null,
            'effective_until' => null,
            'is_active' => $price instanceof InventoryItemPrice ? $price->is_active : true,
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
