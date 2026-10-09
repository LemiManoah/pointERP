<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\InventoryStockBalance;
use App\Services\InventoryStoreStockOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryStockController
{
    public function index(Request $request, InventoryStockBalance $balances, InventoryStoreStockOptions $storeOptions): Response
    {
        Gate::authorize('viewAny', InventoryStockMovement::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $storeIds = $storeOptions->accessibleStoreIds($actor);
        $storeModels = InventoryStore::query()
            ->whereIn('id', $storeIds)
            ->with('branch')
            ->orderBy('name')
            ->get();
        $stores = $storeModels->map(fn (InventoryStore $store): array => ['id' => $store->id, 'name' => $store->name, 'branch_name' => $store->branch->name])->values();
        $items = InventoryItem::query()->where('is_active', true)->with('stockUnit')->orderBy('name')->get();
        $balanceMap = $balances->forStoresAndItems($storeModels->pluck('id')->all(), $items->pluck('id')->all());
        $rows = $storeModels->flatMap(fn (InventoryStore $store) => $items->map(function (InventoryItem $item) use ($store, $balanceMap): array {
            $balance = $balanceMap[$store->id.':'.$item->id];
            $minimum = $item->minimum_stock;

            return ['id' => $store->id.':'.$item->id, 'item_id' => $item->id, 'item_code' => $item->code, 'item_name' => $item->name, 'unit' => $item->stockUnit->symbol ?? $item->stockUnit->name, 'store_id' => $store->id, 'store_name' => $store->name, 'branch_name' => $store->branch->name, 'minimum_stock' => $minimum, 'is_low_stock' => $minimum !== null && (float) $balance['available'] <= (float) $minimum, ...$balance];
        }))->values();

        return Inertia::render('operations/inventory/stock', [
            'rows' => $rows,
            'stores' => $stores,
            'summary' => ['item_store_balances' => $rows->count(), 'stores' => $rows->pluck('store_id')->unique()->count(), 'low_stock' => $rows->where('is_low_stock', true)->count()],
            'canExport' => $actor->can('inventory.reports.export'),
            'canAddStock' => $actor->can('inventory.stock.add'),
        ]);
    }
}
