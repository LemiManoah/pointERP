<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReceiveInventoryStock;
use App\Http\Requests\Operations\Inventory\StoreInventoryGoodsReceiptRequest;
use App\Models\InventoryGoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\BranchContext;
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

        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($actor);
        $canViewCosts = $actor->can('inventory.receipts.view-costs');
        /** @var Collection<int, InventoryGoodsReceipt> $receipts */
        $receipts = InventoryGoodsReceipt::query()
            ->whereIn('branch_id', $branchIds)
            ->with(['store', 'supplier', 'purchaseOrder'])
            ->withCount('lines')
            ->latest('received_on')
            ->limit(100)
            ->get();

        return Inertia::render('operations/inventory/receipts', [
            'receipts' => $receipts->map(fn (InventoryGoodsReceipt $receipt): array => [
                'id' => $receipt->id,
                'reference' => $receipt->reference,
                'received_on' => $receipt->received_on->toDateString(),
                'currency_code' => $canViewCosts ? $receipt->currency_code : null,
                'total_amount' => $canViewCosts ? $receipt->total_amount : null,
                'inspection_status' => $receipt->inspection_status,
                'lines_count' => $receipt->lines_count,
                'store' => $receipt->store->only(['id', 'name']),
                'supplier' => $receipt->supplier->only(['id', 'name']),
                'purchase_order' => $receipt->purchaseOrder->only(['id', 'order_number']),
            ]),
            'canViewCosts' => $canViewCosts,
            'purchaseOrders' => PurchaseOrder::query()
                ->visibleTo($actor)
                ->whereIn('status', ['approved', 'partially_received'])
                ->with(['branch', 'store', 'supplier', 'lines.item', 'lines.unit'])
                ->latest()
                ->get()
                ->map(fn (PurchaseOrder $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'currency_code' => $order->currency_code,
                    'branch' => $order->branch->only(['id', 'name', 'code']),
                    'store' => $order->store->only(['id', 'name', 'code']),
                    'supplier' => $order->supplier->only(['id', 'name', 'code']),
                    'lines' => $order->lines
                        ->filter(fn (PurchaseOrderLine $line): bool => (float) $line->outstandingQuantity() > 0)
                        ->map(fn (PurchaseOrderLine $line): array => [
                            'id' => $line->id,
                            'item_name' => $line->item_name_snapshot,
                            'item_code' => $line->item_code_snapshot,
                            'unit_symbol' => $line->unit_symbol_snapshot,
                            'outstanding_quantity' => $line->outstandingQuantity(),
                            'unit_cost' => $canViewCosts ? $line->unit_price : null,
                            'tracking_type' => $line->item->tracking_type->value,
                            'is_expires' => $line->item->is_expires,
                        ])
                        ->values()
                        ->all(),
                ]),
            'selectedPurchaseOrderId' => $request->string('purchase_order_id')->toString(),
        ]);
    }

    public function store(StoreInventoryGoodsReceiptRequest $request, ReceiveInventoryStock $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $data = $request->validated();
        $purchaseOrder = PurchaseOrder::query()->findOrFail($data['purchase_order_id']);
        Gate::authorize('receive', $purchaseOrder);

        $action->handle($data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order delivery received.']);

        return to_route('inventory.receipts.index');
    }
}
