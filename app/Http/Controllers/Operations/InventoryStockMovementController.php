<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Actions\Operations\Inventory\TransferInventoryStock;
use App\Enums\InventoryMovementType;
use App\Http\Requests\Operations\Inventory\StoreInventoryStockMovementRequest;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryStockMovementController
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('inventory.stock.view'), 403);
        $context = resolve(BranchContext::class);
        $branchIds = $context->accessibleBranchIds($actor);
        $defaultBranch = $context->current($actor) ?? $context->operationalDefault($actor);
        abort_unless($defaultBranch instanceof Branch, 403);

        return Inertia::render('operations/inventory/movements', [
            'movements' => InventoryStockMovement::query()->whereIn('branch_id', $branchIds)->with(['store', 'item.stockUnit', 'postedBy'])->latest('posted_at')->limit(150)->get(),
            'branches' => $context->accessibleBranches($actor)->values(),
            'defaultBranchId' => $defaultBranch->id,
            'canChangeBranch' => $actor->can('inventory.stock.change-branch') && count($branchIds) > 1,
            'stores' => InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get()->map(fn (InventoryStore $store): array => ['id' => $store->id, 'branch_id' => $store->branch_id, 'name' => $store->name, 'item_ids' => InventoryStoreItem::query()->where('inventory_store_id', $store->id)->where('is_active', true)->pluck('inventory_item_id')->all()]),
            'items' => InventoryItem::query()->where('is_active', true)->with('stockUnit')->orderBy('name')->get()->map(fn (InventoryItem $item): array => ['id' => $item->id, 'name' => $item->name, 'code' => $item->code, 'stock_unit_id' => $item->stock_unit_id, 'stock_unit' => $item->stockUnit?->only(['id', 'name', 'symbol']), 'tracking_type' => $item->tracking_type->value]),
            'batches' => InventoryBatch::query()->where('is_active', true)->get(['id', 'inventory_item_id', 'batch_number']),
            'can' => ['adjust' => $actor->can('inventory.stock.adjust'), 'issue' => $actor->can('inventory.stock.issue'), 'return' => $actor->can('inventory.stock.return'), 'transfer' => $actor->can('inventory.stock.transfer'), 'reverse' => $actor->can('inventory.stock.reverse')],
        ]);
    }

    public function store(StoreInventoryStockMovementRequest $request, InventoryStore $inventoryStore, InventoryItem $inventoryItem, PostInventoryStockMovement $action, TransferInventoryStock $transfer): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $movementType = (string) $request->validated('movement_type');
        $context = resolve(BranchContext::class);
        $canChangeBranch = $actor->can('inventory.stock.change-branch') && $context->accessibleBranches($actor)->count() > 1;
        $workingBranch = $context->current($actor) ?? $context->operationalDefault($actor);
        abort_unless($canChangeBranch || $inventoryStore->branch_id === $workingBranch?->id, 403);
        if ($movementType === 'transfer') {
            $destination = InventoryStore::query()->findOrFail((string) $request->validated('destination_store_id'));
            abort_unless($canChangeBranch || $destination->branch_id === $inventoryStore->branch_id, 403);
            Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $inventoryStore, InventoryMovementType::TransferOut]);
            Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $destination, InventoryMovementType::TransferIn]);
            $transfer->handle($inventoryStore, $destination, $inventoryItem, $request->validated(), $actor);
        } else {
            $type = InventoryMovementType::from($movementType);
            Gate::forUser($actor)->authorize('post', [InventoryStockMovement::class, $inventoryStore, $type]);
            $action->handle($inventoryStore, $inventoryItem, $request->validated(), $actor);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock movement recorded.']);

        return $request->validated('return_to') === 'movements'
            ? to_route('inventory.movements.index')
            : to_route('inventory.items.show', ['inventoryItem' => $inventoryItem, 'tab' => 'stock']);
    }
}
