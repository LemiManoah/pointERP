<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReconcileInventoryStockCount;
use App\Enums\InventoryMovementType;
use App\Http\Requests\Operations\Inventory\StoreInventoryStockCountRequest;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\InventoryStoreStockOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryStockCountController
{
    public function index(Request $request, InventoryStoreStockOptions $options): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('inventory.stock.adjust'), 403);

        return Inertia::render('operations/inventory/stock-counts/index', [
            'stores' => $options->stores($actor),
            'countKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(StoreInventoryStockCountRequest $request, InventoryStoreStockOptions $options, ReconcileInventoryStockCount $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $store = InventoryStore::query()->findOrFail((string) $request->validated('inventory_store_id'));
        abort_unless($options->accessibleStoreIds($actor)->contains($store->id), 403);
        Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $store, InventoryMovementType::Adjustment]);

        $posted = $action->handle($store, $request->validated(), $actor);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $posted === 0 ? 'Count recorded. No stock variance was found.' : sprintf('%d stock variance%s reconciled.', $posted, $posted === 1 ? '' : 's'),
        ]);

        return to_route('inventory.movements.index');
    }
}
