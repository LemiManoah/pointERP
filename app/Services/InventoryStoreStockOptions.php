<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryUnitConversion;
use App\Models\User;
use Brick\Math\BigDecimal;
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
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->with(['stockUnit', 'conversions.fromUnit'])
            ->orderBy('name')
            ->get();

        $storeModels = InventoryStore::query()
            ->whereIn('id', $this->accessibleStoreIds($actor))
            ->with('branch')
            ->orderBy('name')
            ->get();
        $storeIds = $storeModels->pluck('id')->all();
        $itemIds = $items->pluck('id')->all();
        $balances = $this->balances->forStoresAndItems($storeIds, $itemIds);
        $batchQuantities = InventoryStockMovement::query()
            ->whereIn('inventory_store_id', $storeIds)
            ->whereIn('inventory_item_id', $itemIds)
            ->whereNotNull('inventory_batch_id')
            ->selectRaw('inventory_store_id, inventory_item_id, inventory_batch_id, SUM(quantity) as quantity')
            ->groupBy('inventory_store_id', 'inventory_item_id', 'inventory_batch_id')
            ->get();
        $batches = InventoryBatch::query()->whereIn('id', $batchQuantities->pluck('inventory_batch_id'))->get()->keyBy('id');

        /** @var Collection<int, array<string, mixed>> $stores */
        $stores = $storeModels
            ->map(fn (InventoryStore $store): array => [
                'id' => $store->id,
                'branch_id' => $store->branch_id,
                'name' => $store->name,
                'code' => $store->code,
                'branch_name' => $store->branch->name,
                'items' => $items
                    ->map(fn (InventoryItem $item): array => [
                        ...$this->itemOption($item),
                        'system_quantity' => $balances[$store->id.':'.$item->id]['on_hand'] ?? '0.0000',
                        'batches' => $batchQuantities
                            ->filter(fn (InventoryStockMovement $row): bool => $row->inventory_store_id === $store->id && $row->inventory_item_id === $item->id && BigDecimal::of((string) $row->quantity)->isGreaterThan(0))
                            ->map(function (InventoryStockMovement $row) use ($batches): ?array {
                                $batch = $batches->get($row->inventory_batch_id);

                                return $batch instanceof InventoryBatch ? [
                                    'id' => $batch->id,
                                    'batch_number' => $batch->batch_number,
                                    'system_quantity' => (string) BigDecimal::of((string) $row->quantity)->toScale(4),
                                ] : null;
                            })
                            ->filter()
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ]);

        return $stores;
    }

    /** @return array<string, mixed> */
    private function itemOption(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'stock_unit_id' => $item->stock_unit_id,
            'unit' => $item->stockUnit->symbol ?? $item->stockUnit->name,
            'tracking_type' => $item->tracking_type->value,
            'is_expires' => $item->is_expires,
            'units' => collect([[
                'id' => $item->stockUnit->id,
                'name' => $item->stockUnit->name,
                'symbol' => $item->stockUnit->symbol,
            ]])->merge($item->conversions->where('is_active', true)->map(fn (InventoryUnitConversion $conversion): array => [
                'id' => $conversion->fromUnit->id,
                'name' => $conversion->fromUnit->name,
                'symbol' => $conversion->fromUnit->symbol,
            ]))->unique('id')->values()->all(),
        ];
    }
}
