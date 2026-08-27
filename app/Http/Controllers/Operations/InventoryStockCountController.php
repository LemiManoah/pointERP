<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReconcileInventoryStockCount;
use App\Http\Requests\Operations\Inventory\StoreInventoryStockCountRequest;
use App\Models\InventoryReconciliation;
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
        abort_unless($actor instanceof User, 403);
        Gate::authorize('viewAny', InventoryReconciliation::class);

        return Inertia::render('operations/inventory/stock-counts/index', [
            'stores' => $options->stores($actor),
            'countKey' => Str::uuid()->toString(),
            'reconciliations' => InventoryReconciliation::query()->whereIn('inventory_store_id', $options->accessibleStoreIds($actor))->with(['store', 'requester', 'lines'])->latest('requested_at')->limit(100)->get()->map(fn (InventoryReconciliation $reconciliation): array => [
                'id' => $reconciliation->id, 'reference' => $reconciliation->reference, 'status' => $reconciliation->status->value,
                'reason' => $reconciliation->reason, 'decision_reason' => $reconciliation->decision_reason,
                'store' => $reconciliation->store->name, 'requested_by' => $reconciliation->requester->name,
                'requested_at' => $reconciliation->requested_at->format('d M Y, H:i'), 'lines_count' => $reconciliation->lines->count(),
                'can_approve' => Gate::forUser($actor)->allows('approve', $reconciliation), 'can_reject' => Gate::forUser($actor)->allows('reject', $reconciliation),
            ]),
            'canCreate' => Gate::forUser($actor)->allows('create', InventoryReconciliation::class),
        ]);
    }

    public function store(StoreInventoryStockCountRequest $request, InventoryStoreStockOptions $options, ReconcileInventoryStockCount $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', InventoryReconciliation::class);
        $store = InventoryStore::query()->findOrFail((string) $request->validated('inventory_store_id'));
        abort_unless($options->accessibleStoreIds($actor)->contains($store->id), 403);
        $action->handle($store, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reconciliation submitted for approval. Stock has not changed yet.']);

        return to_route('inventory.stock-counts.index');
    }
}
