<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReceiveInventoryStock;
use App\Http\Requests\Operations\Inventory\StoreInventoryGoodsReceiptRequest;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryGoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseOrderReceiptOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryGoodsReceiptController
{
    public function index(Request $request, PurchaseOrderReceiptOptions $options): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', InventoryGoodsReceipt::class);

        return Inertia::render('operations/inventory/receipts', [
            ...$options->for($actor, $request->string('purchase_order_id')->toString()),
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

    public function show(InventoryGoodsReceipt $inventoryGoodsReceipt): Response
    {
        Gate::authorize('view', $inventoryGoodsReceipt);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $canViewCosts = $actor->can('inventory.receipts.view-costs');
        $inventoryGoodsReceipt->load([
            'branch',
            'store',
            'supplier',
            'purchaseOrder',
            'receiver',
            'verifier',
            'lines.item',
            'lines.unit',
        ]);
        $tenant = Tenant::query()->findOrFail($inventoryGoodsReceipt->tenant_id);

        return Inertia::render('operations/inventory/receipts/show', [
            'receipt' => [
                'id' => $inventoryGoodsReceipt->id,
                'reference' => $inventoryGoodsReceipt->reference,
                'supplier_reference' => $inventoryGoodsReceipt->supplier_reference,
                'received_on' => $inventoryGoodsReceipt->received_on->format('d M Y'),
                'recorded_at' => $inventoryGoodsReceipt->created_at->format('d M Y, H:i'),
                'currency_code' => $canViewCosts ? $inventoryGoodsReceipt->currency_code : null,
                'total_amount' => $canViewCosts ? $inventoryGoodsReceipt->total_amount : null,
                'inspection_status' => $inventoryGoodsReceipt->inspection_status,
                'notes' => $inventoryGoodsReceipt->notes,
                'company_name' => $tenant->name,
                'branch_name' => $inventoryGoodsReceipt->branch->name,
                'store_name' => $inventoryGoodsReceipt->store->name,
                'supplier_name' => $inventoryGoodsReceipt->supplier->name,
                'receiver_name' => $inventoryGoodsReceipt->receiver->name,
                'verifier_name' => $inventoryGoodsReceipt->verifier->name,
                'purchase_order' => $inventoryGoodsReceipt->purchaseOrder->only(['id', 'order_number']),
                'lines' => $inventoryGoodsReceipt->lines->map(fn (InventoryGoodsReceiptLine $line): array => [
                    'id' => $line->id,
                    'item_name' => $line->item->name,
                    'item_code' => $line->item->code,
                    'quantity' => $line->quantity,
                    'accepted_quantity' => $line->accepted_quantity,
                    'rejected_quantity' => $line->rejected_quantity,
                    'rejection_reason' => $line->rejection_reason,
                    'unit' => $line->unit->symbol ?? $line->unit->code,
                    'batch_number' => $line->batch_number,
                    'expires_on' => $line->expires_on?->format('d M Y'),
                    'unit_cost' => $canViewCosts ? $line->unit_cost : null,
                    'line_total' => $canViewCosts ? $line->line_total : null,
                ]),
            ],
            'canViewCosts' => $canViewCosts,
        ]);
    }
}
