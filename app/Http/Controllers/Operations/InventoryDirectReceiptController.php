<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Enums\InventoryDirectReceiptReason;
use App\Actions\Operations\Inventory\AddInventoryStock;
use App\Http\Requests\Operations\Inventory\StoreInventoryDirectReceiptRequest;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\InventoryDirectReceipt;
use App\Models\InventoryDirectReceiptLine;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\InventoryStoreStockOptions;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryDirectReceiptController
{
    public function create(Request $request, InventoryStoreStockOptions $options): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', InventoryDirectReceipt::class);

        $stores = $options->stores($actor);
        $branchContext = resolve(BranchContext::class);
        $defaultBranch = $branchContext->current($actor) ?? $branchContext->operationalDefault($actor);
        $defaultStore = $defaultBranch instanceof Branch
            ? $stores->first(fn (array $store): bool => $store['branch_id'] === $defaultBranch->id)
            : null;
        $defaultStore ??= $stores->first();
        $defaultStoreId = is_array($defaultStore) && is_string($defaultStore['id'] ?? null)
            ? $defaultStore['id']
            : null;
        $returnTo = $this->returnTo($request);

        return Inertia::render('operations/inventory/direct-receipts/create', [
            'stores' => $stores,
            'defaultStoreId' => $defaultStoreId,
            'companies' => Customer::query()->visibleTo($actor)->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'name', 'code', 'type']),
            'receiptKey' => Str::uuid()->toString(),
            'receivedOn' => now()->toDateString(),
            'reasons' => collect(InventoryDirectReceiptReason::cases())->map(fn (InventoryDirectReceiptReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()]),
            'returnTo' => $returnTo,
        ]);
    }

    public function store(StoreInventoryDirectReceiptRequest $request, InventoryStoreStockOptions $options, AddInventoryStock $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', InventoryDirectReceipt::class);

        $store = InventoryStore::query()->findOrFail((string) $request->validated('inventory_store_id'));
        abort_unless($options->accessibleStoreIds($actor)->contains($store->id), 403);
        $receipt = $action->handle($store, $request->validated(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock added successfully under '.$receipt->reference.'.']);

        return redirect()->to((string) $request->validated('return_to'));
    }

    public function show(InventoryDirectReceipt $inventoryDirectReceipt): Response
    {
        Gate::authorize('view', $inventoryDirectReceipt);
        $inventoryDirectReceipt->load(['branch', 'store', 'sourceCompany', 'receiver', 'lines.item.stockUnit', 'lines.unit']);

        return Inertia::render('operations/inventory/direct-receipts/show', [
            'receipt' => [
                'id' => $inventoryDirectReceipt->id,
                'reference' => $inventoryDirectReceipt->reference,
                'source_reference' => $inventoryDirectReceipt->source_reference,
                'received_on' => $inventoryDirectReceipt->received_on->format('d M Y'),
                'reason' => $inventoryDirectReceipt->reason->label(),
                'branch' => $inventoryDirectReceipt->branch->name,
                'store' => $inventoryDirectReceipt->store->name,
                'source_company' => $inventoryDirectReceipt->sourceCompany?->name,
                'received_by' => $inventoryDirectReceipt->receiver->name,
                'lines' => $inventoryDirectReceipt->lines->map(fn (InventoryDirectReceiptLine $line): array => [
                    'id' => $line->id,
                    'item_name' => $line->item_name_snapshot,
                    'item_code' => $line->item_code_snapshot,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit_symbol_snapshot ?? $line->unit->name,
                    'stock_quantity' => $line->stock_quantity,
                    'stock_unit' => $line->item->stockUnit->symbol ?? $line->item->stockUnit->name,
                    'batch_number' => $line->batch_number,
                    'expires_on' => $line->expires_on?->format('d M Y'),
                ])->values(),
            ],
        ]);
    }

    private function returnTo(Request $request): string
    {
        $requested = $request->string('return_to')->toString();
        if ($this->isReturnPath($requested)) {
            return $requested;
        }

        $previous = parse_url(url()->previous(), PHP_URL_PATH);

        return is_string($previous) && $this->isReturnPath($previous)
            ? $previous
            : '/inventory/stock';
    }

    private function isReturnPath(string $path): bool
    {
        return $path !== '/inventory/add-stock'
            && preg_match('/^\/(?!\/)[A-Za-z0-9\/_-]*$/', $path) === 1;
    }
}
