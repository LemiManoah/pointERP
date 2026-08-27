<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ManagePurchaseOrder;
use App\Actions\Operations\Inventory\SavePurchaseOrder;
use App\Http\Requests\Operations\Inventory\SavePurchaseOrderRequest;
use App\Models\InventoryGoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\ProcurementFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseOrderController
{
    public function index(Request $request, ProcurementFormOptions $options): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $canViewCosts = $actor->can('inventory.purchase-orders.view-costs');
        $orders = PurchaseOrder::query()->visibleTo($actor)->with(['branch', 'store', 'supplier'])->withCount('lines')->latest('order_date')->limit(200)->get();

        return Inertia::render('operations/inventory/purchase-orders/index', [
            'purchaseOrders' => $orders->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'branch_name' => $order->branch->name,
                'store_name' => $order->store->name,
                'supplier_name' => $order->supplier_name_snapshot,
                'order_date' => $order->order_date->toDateString(),
                'expected_date' => $order->expected_date?->toDateString(),
                'currency_code' => $canViewCosts ? $order->currency_code : null,
                'total_amount' => $canViewCosts ? $order->total_amount : null,
                'status' => $order->status->value,
                'lines_count' => $order->lines_count,
            ]),
            ...$options->for($actor),
            'canCreate' => Gate::forUser($actor)->allows('create', PurchaseOrder::class),
            'canViewCosts' => $canViewCosts,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder, ProcurementFormOptions $options): Response
    {
        Gate::authorize('view', $purchaseOrder);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $canViewCosts = $actor->can('inventory.purchase-orders.view-costs');
        $purchaseOrder->load(['branch', 'store', 'supplier', 'approver', 'lines', 'receipts']);

        return Inertia::render('operations/inventory/purchase-orders/show', [
            'purchaseOrder' => [
                ...$purchaseOrder->only(['id', 'branch_id', 'inventory_store_id', 'supplier_id', 'order_number', 'supplier_name_snapshot', 'currency_code', 'status', 'discount_amount', 'tax_amount', 'delivery_terms', 'payment_terms', 'notes', 'decision_reason']),
                'order_date' => $purchaseOrder->order_date->toDateString(),
                'expected_date' => $purchaseOrder->expected_date?->toDateString(),
                'status' => $purchaseOrder->status->value,
                'branch_name' => $purchaseOrder->branch->name,
                'store_name' => $purchaseOrder->store->name,
                'approved_by' => $purchaseOrder->approver?->name,
                'subtotal' => $canViewCosts ? $purchaseOrder->subtotal : null,
                'discount_amount' => $canViewCosts ? $purchaseOrder->discount_amount : null,
                'tax_amount' => $canViewCosts ? $purchaseOrder->tax_amount : null,
                'total_amount' => $canViewCosts ? $purchaseOrder->total_amount : null,
                'lines' => $purchaseOrder->lines->map(fn (PurchaseOrderLine $line): array => [
                    ...$line->only(['id', 'inventory_item_id', 'unit_of_measure_id', 'item_code_snapshot', 'item_name_snapshot', 'unit_symbol_snapshot', 'ordered_quantity', 'accepted_quantity', 'rejected_quantity', 'price_source']),
                    'unit_price' => $canViewCosts ? $line->unit_price : null,
                    'line_amount' => $canViewCosts ? $line->line_amount : null,
                    'outstanding_quantity' => $line->outstandingQuantity(),
                ]),
                'receipts' => $purchaseOrder->receipts->map(fn (InventoryGoodsReceipt $receipt): array => $receipt->only(['id', 'reference', 'received_on', 'inspection_status'])),
            ],
            'procurementOptions' => $options->for($actor),
            'can' => [
                'update' => Gate::forUser($actor)->allows('update', $purchaseOrder),
                'submit' => Gate::forUser($actor)->allows('submit', $purchaseOrder),
                'approve' => Gate::forUser($actor)->allows('approve', $purchaseOrder),
                'cancel' => Gate::forUser($actor)->allows('cancel', $purchaseOrder),
                'close' => Gate::forUser($actor)->allows('close', $purchaseOrder),
                'receive' => Gate::forUser($actor)->allows('receive', $purchaseOrder),
                'viewCosts' => $canViewCosts,
            ],
        ]);
    }

    public function store(SavePurchaseOrderRequest $request, SavePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('create', PurchaseOrder::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $order = $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order saved as a draft.']);

        return to_route('inventory.purchase-orders.show', $order);
    }

    public function update(SavePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('update', $purchaseOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $purchaseOrder);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order updated.']);

        return to_route('inventory.purchase-orders.show', $purchaseOrder);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, ManagePurchaseOrder $action): RedirectResponse
    {
        Gate::authorize('cancel', $purchaseOrder);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->cancel($purchaseOrder, (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order cancelled.']);

        return back();
    }
}
