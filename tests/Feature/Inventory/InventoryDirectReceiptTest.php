<?php

declare(strict_types=1);

use App\Enums\InventoryDirectReceiptReason;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\InventoryDirectReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
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

it('guards direct stock receipts and posts an idempotent receipt movement', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $before = (float) resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'];
    $receiptKey = fake()->uuid();
    $payload = [
        'receipt_key' => $receiptKey,
        'return_to' => '/inventory/stock',
        'inventory_store_id' => $store->id,
        'received_on' => now()->toDateString(),
        'reason' => InventoryDirectReceiptReason::OpeningBalance->value,
        'lines' => [[
            'inventory_item_id' => $item->id,
            'unit_of_measure_id' => $item->stock_unit_id,
            'quantity' => '12',
        ]],
    ];

    $this->actingAs($siteManager)->get(route('inventory.direct-receipts.create'))->assertForbidden();
    $this->actingAs($storeKeeper)->get(route('inventory.direct-receipts.create'))->assertOk();
    $this->actingAs($storeKeeper)->post(route('inventory.direct-receipts.store'), $payload)->assertRedirect('/inventory/stock');
    $this->actingAs($storeKeeper)->post(route('inventory.direct-receipts.store'), $payload)->assertRedirect('/inventory/stock');

    $receipt = InventoryDirectReceipt::query()->where('receipt_key', $receiptKey)->firstOrFail();
    expect(InventoryDirectReceipt::query()->where('receipt_key', $receiptKey)->count())->toBe(1)
        ->and(InventoryStockMovement::query()->where('source_type', InventoryDirectReceipt::class)->where('source_id', $receipt->id)->count())->toBe(1)
        ->and((float) resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe($before + 12);
});

it('records a batch while adding batch-tracked stock', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $batchNumber = 'TEST-BATCH-'.fake()->numerify('####');

    $this->actingAs($storeKeeper)->post(route('inventory.direct-receipts.store'), [
        'receipt_key' => fake()->uuid(),
        'return_to' => '/inventory/stock-movements',
        'inventory_store_id' => $store->id,
        'received_on' => now()->toDateString(),
        'reason' => InventoryDirectReceiptReason::Donation->value,
        'lines' => [[
            'inventory_item_id' => $item->id,
            'unit_of_measure_id' => $item->stock_unit_id,
            'quantity' => '20',
            'batch_number' => $batchNumber,
            'manufactured_on' => now()->subMonth()->toDateString(),
            'expires_on' => now()->addMonths(6)->toDateString(),
        ]],
    ])->assertRedirect();

    $batch = InventoryBatch::query()->where('inventory_item_id', $item->id)->where('batch_number', $batchNumber)->firstOrFail();
    expect(InventoryStockMovement::query()->where('inventory_batch_id', $batch->id)->value('quantity'))->toBe('20.0000');
});

it('allows an active company of any type to supply a purchase order', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->with('branch')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $company = Customer::factory()->create([
        'tenant_id' => $store->tenant_id,
        'branch_id' => $store->branch_id,
        'type' => Customer::TYPE_CLIENT,
        'status' => 'active',
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    $this->actingAs($director)->post(route('inventory.purchase-orders.store'), [
        'branch_id' => $store->branch_id,
        'inventory_store_id' => $store->id,
        'supplier_id' => $company->id,
        'order_date' => now()->toDateString(),
        'currency_code' => $store->branch->default_currency_code,
        'lines' => [[
            'inventory_item_id' => $item->id,
            'unit_of_measure_id' => $item->stock_unit_id,
            'ordered_quantity' => '5',
            'unit_price' => (string) $item->default_unit_cost,
        ]],
    ])->assertRedirect();

    expect(PurchaseOrder::query()->where('supplier_id', $company->id)->exists())->toBeTrue();
});
