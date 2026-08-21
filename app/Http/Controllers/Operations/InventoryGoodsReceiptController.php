<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReceiveInventoryStock;
use App\Http\Requests\Operations\Inventory\StoreInventoryGoodsReceiptRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryGoodsReceiptController
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('viewAny', InventoryGoodsReceipt::class);
        $context = resolve(BranchContext::class);
        $branchIds = $context->accessibleBranchIds($actor);
        $defaultBranch = $context->current($actor) ?? $context->operationalDefault($actor);
        abort_unless($defaultBranch instanceof Branch, 403);
        $canViewCosts = $actor->can('inventory.receipts.view-costs');
        /** @var Collection<int, InventoryGoodsReceipt> $receipts */
        $receipts = InventoryGoodsReceipt::query()->whereIn('branch_id', $branchIds)->with(['store', 'supplier'])->withCount('lines')->latest('received_on')->limit(100)->get();

        return Inertia::render('operations/inventory/receipts', [
            'receipts' => $receipts->map(fn (InventoryGoodsReceipt $receipt): array => [
                'id' => $receipt->id, 'reference' => $receipt->reference, 'received_on' => $receipt->received_on->toDateString(),
                'currency_code' => $canViewCosts ? $receipt->currency_code : null, 'total_amount' => $canViewCosts ? $receipt->total_amount : null,
                'amount_paid' => $canViewCosts ? $receipt->amount_paid : null, 'payment_status' => $canViewCosts ? $receipt->payment_status->value : null,
                'lines_count' => $receipt->lines_count, 'store' => $receipt->store->only(['id', 'name']), 'supplier' => $receipt->supplier->only(['id', 'name']),
            ]),
            'branches' => $context->accessibleBranches($actor)->values(),
            'defaultBranchId' => $defaultBranch->id,
            'canChangeBranch' => $actor->can('inventory.stock.change-branch') && count($branchIds) > 1,
            'canViewCosts' => $canViewCosts,
            'stores' => InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name', 'code']),
            'items' => InventoryItem::query()->where('is_active', true)->with('stockUnit')->orderBy('name')->get()->map(fn (InventoryItem $item): array => ['id' => $item->id, 'name' => $item->name, 'code' => $item->code, 'tracking_type' => $item->tracking_type->value, 'is_expires' => $item->is_expires, 'stock_unit_id' => $item->stock_unit_id, 'stock_unit' => $item->stockUnit?->only(['id', 'name', 'symbol'])]),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', resolve(TenantContext::class)->id()))->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'symbol']),
            'suppliers' => Customer::query()->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreInventoryGoodsReceiptRequest $request, ReceiveInventoryStock $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', InventoryGoodsReceipt::class);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock receipt recorded.']);

        return to_route('inventory.receipts.index');
    }
}
