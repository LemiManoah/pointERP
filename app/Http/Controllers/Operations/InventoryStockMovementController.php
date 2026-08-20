<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Enums\InventoryMovementType;
use App\Http\Requests\Operations\Inventory\StoreInventoryStockMovementRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class InventoryStockMovementController
{
    public function store(StoreInventoryStockMovementRequest $request, InventoryStore $inventoryStore, InventoryItem $inventoryItem, PostInventoryStockMovement $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $type = InventoryMovementType::from((string) $request->validated('movement_type'));
        Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $inventoryStore, $type]);
        $action->handle($inventoryStore, $inventoryItem, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock movement posted.']);

        return to_route('inventory.items.show', ['inventoryItem' => $inventoryItem, 'tab' => 'stock']);
    }
}
