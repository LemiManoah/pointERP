<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryStockBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryStockController
{
    public function index(Request $request, InventoryStockBalance $balances): Response
    {
        Gate::authorize('viewAny', InventoryStockMovement::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($actor);
        $storeIds = InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->pluck('id');
        $rows = InventoryStoreItem::query()->where('is_active', true)->whereIn('inventory_store_id', $storeIds)->with(['store.branch', 'item.stockUnit'])->get()->map(function (InventoryStoreItem $setting) use ($balances): array {
            $balance = $balances->for($setting->store, $setting->item);
            $minimum = $setting->minimum_stock ?? $setting->item->minimum_stock;

            return ['id' => $setting->id, 'item_id' => $setting->item->id, 'item_code' => $setting->item->code, 'item_name' => $setting->item->name, 'unit' => $setting->item->stockUnit->symbol ?? $setting->item->stockUnit->name, 'store_name' => $setting->store->name, 'branch_name' => $setting->store->branch->name, 'minimum_stock' => $minimum, 'is_low_stock' => $minimum !== null && (float) $balance['on_hand'] <= (float) $minimum, ...$balance];
        })->values();

        return Inertia::render('operations/inventory/stock', [
            'rows' => $rows,
            'summary' => ['stocked_items' => $rows->count(), 'stores' => $rows->pluck('store_name')->unique()->count(), 'low_stock' => $rows->where('is_low_stock', true)->count()],
            'canExport' => $actor->can('inventory.reports.export'),
        ]);
    }
}
