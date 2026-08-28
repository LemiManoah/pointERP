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
use App\Services\PurchaseOrderReceiptOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseOrderController
{
    public function index(Request $request, ProcurementFormOptions $formOptions, PurchaseOrderReceiptOptions $receiptOptions): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $canViewCosts = $actor->can('inventory.purchase-orders.view-costs');
        $orders = PurchaseOrder::query()->visibleTo($actor)->with(['branch', 'store', 'supplier'])->withCount('lines')->latest('order_date')->limit(200)->get();

        $canCreate = Gate::forUser($actor)->allows('create', PurchaseOrder::class);
        $canReceive = Gate::forUser($actor)->allows('create', InventoryGoodsReceipt::class);

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
            'canCreate' => $canCreate,
            'canReceive' => $canReceive,
            'canViewCosts' => $canViewCosts,
            'purchaseOrderOptions' => $canCreate ? $formOptions->for($actor) : null,
            'receiptOptions' => $canReceive ? $receiptOptions->for($actor, $request->string('purchase_order_id')->toString()) : null,
        ]);
    }

    public function create(Request $request, ProcurementFormOptions $options): Response
    {
        Gate::authorize('create', PurchaseOrder::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return Inertia::render('operations/inventory/purchase-orders/form', [
            'purchaseOrder' => null,
            'options' => $options->for($actor),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): Response
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
                'receipts' => $purchaseOrder->receipts->map(fn (InventoryGoodsReceipt $receipt): array => [
                    ...$receipt->only(['id', 'reference', 'inspection_status']),
                    'received_on' => $receipt->received_on->format('d M Y'),
                ]),
            ],
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

    public function edit(Request $request, PurchaseOrder $purchaseOrder, ProcurementFormOptions $options): Response
    {
        Gate::authorize('update', $purchaseOrder);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $purchaseOrder->load('lines');

        return Inertia::render('operations/inventory/purchase-orders/form', [
            'purchaseOrder' => [
                ...$purchaseOrder->only(['id', 'branch_id', 'inventory_store_id', 'supplier_id', 'currency_code', 'discount_amount', 'tax_amount', 'delivery_terms', 'payment_terms', 'notes']),
                'order_number' => $purchaseOrder->order_number,
                'order_date' => $purchaseOrder->order_date->toDateString(),
                'expected_date' => $purchaseOrder->expected_date?->toDateString(),
                'lines' => $purchaseOrder->lines->map(fn (PurchaseOrderLine $line): array => $line->only(['inventory_item_id', 'unit_of_measure_id', 'ordered_quantity', 'unit_price']))->values()->all(),
            ],
            'options' => $options->for($actor),
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
