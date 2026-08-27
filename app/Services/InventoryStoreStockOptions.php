<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryTrackingType;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class InventoryStoreStockOptions
{
    public function __construct(private BranchContext $branchContext, private InventoryStockBalance $balances) {}

    /** @return Collection<int, string> */
    public function accessibleStoreIds(User $actor): Collection
    {
        $branchIds = $this->branchContext->accessibleBranchIds($actor);
        $workingBranch = $this->branchContext->current($actor) ?? $this->branchContext->operationalDefault($actor);
        if (! $actor->can('inventory.stock.change-branch') || count($branchIds) < 2) {
            $branchIds = $workingBranch instanceof Branch ? [$workingBranch->id] : [];
        }

        return InventoryStore::query()
            ->whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('id');
    }

    /** @return Collection<int, array<string, mixed>> */
    public function stores(User $actor): Collection
    {
        return InventoryStore::query()
            ->whereIn('id', $this->accessibleStoreIds($actor))
            ->with(['branch', 'storeSettings.item.stockUnit'])
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryStore $store): array => [
                'id' => $store->id,
                'branch_id' => $store->branch_id,
                'name' => $store->name,
                'code' => $store->code,
                'branch_name' => $store->branch->name,
                'items' => $store->storeSettings
                    ->filter(fn (InventoryStoreItem $setting): bool => $setting->is_active && $setting->item->is_active)
                    ->map(fn (InventoryStoreItem $setting): array => $this->itemOption($store, $setting))
                    ->values(),
            ]);
    }

    /** @return array<string, mixed> */
    private function itemOption(InventoryStore $store, InventoryStoreItem $setting): array
    {
        $item = $setting->item;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'stock_unit_id' => $item->stock_unit_id,
            'unit' => $item->stockUnit->symbol ?? $item->stockUnit->name,
            'tracking_type' => $item->tracking_type->value,
            'system_quantity' => $this->balances->for($store, $item)['on_hand'],
            'batches' => $item->tracking_type === InventoryTrackingType::Batch
                ? InventoryBatch::query()->where('inventory_item_id', $item->id)->where('is_active', true)->orderBy('batch_number')->get()->map(fn (InventoryBatch $batch): array => [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'system_quantity' => $this->balances->forBatch($store, $item, $batch->id),
                ])->values()
                : [],
        ];
    }
}
