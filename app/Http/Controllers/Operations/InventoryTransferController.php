<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\TransferInventoryItems;
use App\Http\Requests\Operations\Inventory\StoreInventoryTransferRequest;
use App\Models\InventoryStore;
use App\Models\InventoryTransfer;
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
        abort_unless($actor instanceof User, 403);
        Gate::authorize('viewAny', InventoryTransfer::class);

        return Inertia::render('operations/inventory/transfers/index', [
            'stores' => $options->stores($actor),
            'transferKey' => Str::uuid()->toString(),
            'transfers' => InventoryTransfer::query()->whereIn('source_store_id', $options->accessibleStoreIds($actor))->with(['sourceStore', 'destinationStore', 'requester', 'lines'])->latest('requested_at')->limit(100)->get()
                ->filter(fn (InventoryTransfer $transfer): bool => Gate::forUser($actor)->allows('view', $transfer))
                ->values()
                ->map(fn (InventoryTransfer $transfer): array => [
                    'id' => $transfer->id, 'reference' => $transfer->reference, 'status' => $transfer->status->value,
                    'reason' => $transfer->reason, 'decision_reason' => $transfer->decision_reason,
                    'source_store' => $transfer->sourceStore->name, 'destination_store' => $transfer->destinationStore->name,
                    'requested_by' => $transfer->requester->name, 'requested_at' => $transfer->requested_at->format('d M Y, H:i'),
                    'lines_count' => $transfer->lines->count(),
                    'can_approve' => Gate::forUser($actor)->allows('approve', $transfer), 'can_reject' => Gate::forUser($actor)->allows('reject', $transfer),
                ]),
            'canCreate' => Gate::forUser($actor)->allows('create', InventoryTransfer::class),
        ]);
    }

    public function store(StoreInventoryTransferRequest $request, InventoryStoreStockOptions $options, TransferInventoryItems $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', InventoryTransfer::class);
        $allowedStoreIds = $options->accessibleStoreIds($actor);
        $source = InventoryStore::query()->findOrFail((string) $request->validated('source_store_id'));
        $destination = InventoryStore::query()->findOrFail((string) $request->validated('destination_store_id'));
        abort_unless($allowedStoreIds->contains($source->id) && $allowedStoreIds->contains($destination->id), 403);
        $action->handle($source, $destination, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer submitted for approval. Stock has not changed yet.']);

        return to_route('inventory.transfers.index');
    }
}
