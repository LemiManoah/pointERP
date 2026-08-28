<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;

final class PurchaseOrderReceiptOptions
{
    /** @return array<string, mixed> */
    public function for(User $actor, string $selectedPurchaseOrderId = ''): array
    {
        $canViewCosts = $actor->can('inventory.receipts.view-costs');

        return [
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
            'selectedPurchaseOrderId' => $selectedPurchaseOrderId,
        ];
    }
}
