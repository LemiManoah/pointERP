<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReverseInventoryStockMovement;
use App\Http\Requests\Operations\Inventory\ReverseInventoryStockMovementRequest;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryStockMovementReversalController
{
    public function __invoke(ReverseInventoryStockMovementRequest $request, InventoryStockMovement $inventoryStockMovement, ReverseInventoryStockMovement $action): RedirectResponse
    {
        Gate::authorize('reverse', $inventoryStockMovement);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $item = $inventoryStockMovement->inventory_item_id;
        $action->handle($inventoryStockMovement, $actor, (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock movement reversed.']);

        return to_route('inventory.items.show', ['inventoryItem' => $item, 'tab' => 'stock']);
    }
}
