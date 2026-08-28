<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\AuditActivity;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows procurement records only to authorised branch users', function (): void {
    $procurementOfficer = User::query()->where('email', 'procurement.kla@point.test')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($procurementOfficer)->get(route('inventory.purchase-orders.index'))->assertOk();
    $this->actingAs($siteManager)->get(route('inventory.purchase-orders.index'))->assertForbidden();
});

it('guards the purchase order receiving workspace with stock receipt permission', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $procurementOfficer = User::query()->where('email', 'procurement.kla@point.test')->firstOrFail();

    $this->actingAs($storeKeeper)->get(route('inventory.receipts.index'))->assertOk();
    $this->actingAs($procurementOfficer)->get(route('inventory.receipts.index'))->assertForbidden();
});

it('allows permission-based self approval and makes the approved order immutable', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $order = PurchaseOrder::query()->where('order_number', 'PO-2026-DEMO01')->firstOrFail();
    $order->forceFill(['status' => PurchaseOrderStatus::Draft])->save();

    $this->actingAs($director)->post(route('inventory.purchase-orders.submit', $order))->assertRedirect();
    $this->actingAs($director)->post(route('inventory.purchase-orders.review', $order), ['decision' => 'approve'])->assertRedirect();

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($order->approved_by)->toBe($director->id)
        ->and(AuditActivity::query()->where('event', 'inventory.purchase_order.approve')->exists())->toBeTrue();

    $this->actingAs($director)->post(route('inventory.purchase-orders.submit', $order))->assertForbidden();
});

it('posts only accepted PO quantities and keeps rejected quantities outside on-hand', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $order = PurchaseOrder::query()->where('order_number', 'PO-2026-DEMO01')->with('lines')->firstOrFail();
    $line = $order->lines->firstOrFail();
    $item = InventoryItem::query()->where('code', 'PPE-VEST')->firstOrFail();
    $before = resolve(InventoryStockBalance::class)->for($order->store, $item)['on_hand'];
    $supplierReference = 'TEST-DELIVERY-'.fake()->uuid();

    $this->actingAs($storeKeeper)->post(route('inventory.receipts.store'), [
        'purchase_order_id' => $order->id,
        'supplier_reference' => $supplierReference,
        'received_on' => now()->toDateString(),
        'lines' => [[
            'purchase_order_line_id' => $line->id,
            'quantity' => '25',
            'accepted_quantity' => '20',
            'rejected_quantity' => '5',
            'rejection_reason' => 'Five vests failed the reflective-strip inspection.',
        ]],
    ])->assertRedirect(route('inventory.receipts.index'));

    $receipt = InventoryGoodsReceipt::query()->where('supplier_reference', $supplierReference)->firstOrFail();

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($line->refresh()->accepted_quantity)->toBe('20.0000')
        ->and($line->rejected_quantity)->toBe('5.0000')
        ->and(resolve(InventoryStockBalance::class)->for($order->store, $item)['on_hand'])->toBe(((float) $before + 20).'.0000')
        ->and(InventoryStockMovement::query()->where('source_type', InventoryGoodsReceipt::class)->where('source_id', $receipt->id)->where('inventory_item_id', $item->id)->value('quantity'))->toBe('20.0000');
});
