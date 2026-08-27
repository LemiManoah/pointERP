<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\TransferInventoryItems;
use App\Enums\InventoryMovementType;
use App\Http\Requests\Operations\Inventory\StoreInventoryTransferRequest;
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

final class InventoryTransferController
{
    public function index(Request $request, InventoryStoreStockOptions $options): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('inventory.stock.transfer'), 403);

        return Inertia::render('operations/inventory/transfers/index', [
            'stores' => $options->stores($actor),
            'transferKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(StoreInventoryTransferRequest $request, InventoryStoreStockOptions $options, TransferInventoryItems $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $allowedStoreIds = $options->accessibleStoreIds($actor);
        $source = InventoryStore::query()->findOrFail((string) $request->validated('source_store_id'));
        $destination = InventoryStore::query()->findOrFail((string) $request->validated('destination_store_id'));
        abort_unless($allowedStoreIds->contains($source->id) && $allowedStoreIds->contains($destination->id), 403);
        Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $source, InventoryMovementType::TransferOut]);
        Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $destination, InventoryMovementType::TransferIn]);

        $action->handle($source, $destination, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store transfer recorded. Source and destination balances are now updated.']);

        return to_route('inventory.movements.index');
    }
}
