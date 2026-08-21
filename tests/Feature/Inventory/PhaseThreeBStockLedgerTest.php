<?php

declare(strict_types=1);

use App\Models\AuditActivity;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
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

it('posts an idempotent issue and blocks negative stock', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'BAG')->firstOrFail();
    $batch = InventoryBatch::query()->where('inventory_item_id', $item->id)->firstOrFail();
    $payload = ['movement_type' => 'issue', 'original_quantity' => '100', 'original_unit_id' => $unit->id, 'inventory_batch_id' => $batch->id, 'source_key' => 'test:cement:issue:001', 'reason' => 'Issue cement for drainage works.'];

    $this->actingAs($storeKeeper)->post(route('inventory.stock-movements.store', [$store, $item]), $payload)->assertRedirect();
    $this->actingAs($storeKeeper)->post(route('inventory.stock-movements.store', [$store, $item]), $payload)->assertRedirect();

    expect(InventoryStockMovement::query()->where('source_key', 'test:cement:issue:001')->count())->toBe(1)
        ->and(resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe('1100.0000');

    $this->actingAs($storeKeeper)->post(route('inventory.stock-movements.store', [$store, $item]), [...$payload, 'source_key' => 'test:cement:issue:negative', 'original_quantity' => '2000'])->assertSessionHasErrors('original_quantity');
});

it('reverses by posting an opposite movement and preserves the original', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $opening = InventoryStockMovement::query()->where('source_key', 'seed:opening:agg-20:kla')->firstOrFail();

    $this->actingAs($director)->post(route('inventory.stock-movements.reverse', $opening), ['reason' => 'Opening quantity was entered against the wrong count sheet.'])->assertRedirect();

    expect($opening->refresh()->status->value)->toBe('reversed')
        ->and(InventoryStockMovement::query()->where('reversal_of_id', $opening->id)->value('quantity'))->toBe('-180.0000')
        ->and(resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe('0.0000')
        ->and(AuditActivity::query()->where('event', 'inventory.stock.reversed')->exists())->toBeTrue();

    expect(fn () => $opening->update(['quantity' => '999.0000']))->toThrow(LogicException::class);
});

it('forbids stock mutations without the required permission', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'TONNE')->firstOrFail();

    $this->actingAs($siteManager)->post(route('inventory.stock-movements.store', [$store, $item]), ['movement_type' => 'adjustment', 'adjustment_direction' => 'increase', 'original_quantity' => '1', 'original_unit_id' => $unit->id, 'source_key' => 'test:forbidden', 'reason' => 'Must not post.'])->assertForbidden();
});

it('receives several supplier items through one goods receipt', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $supplier = Customer::query()->where('code', 'SUP-DEMO')->firstOrFail();
    $aggregate = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $tonne = UnitOfMeasure::query()->where('code', 'TONNE')->firstOrFail();

    $this->actingAs($director)->post(route('inventory.receipts.store'), [
        'inventory_store_id' => $store->id,
        'supplier_id' => $supplier->id,
        'received_on' => now()->toDateString(),
        'amount_paid' => '100000',
        'lines' => [[
            'inventory_item_id' => $aggregate->id,
            'quantity' => '2',
            'unit_of_measure_id' => $tonne->id,
            'unit_cost' => '130000',
        ]],
    ])->assertRedirect(route('inventory.receipts.index'));

    $receipt = InventoryGoodsReceipt::query()->latest()->firstOrFail();
    expect($receipt->total_amount)->toBe('260000.0000')
        ->and($receipt->amount_paid)->toBe('100000.0000')
        ->and($receipt->payment_status->value)->toBe('partially_paid')
        ->and($receipt->lines)->toHaveCount(1)
        ->and(resolve(InventoryStockBalance::class)->for($store, $aggregate)['on_hand'])->toBe('182.0000');
});
