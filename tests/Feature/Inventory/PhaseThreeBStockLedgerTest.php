<?php

declare(strict_types=1);

use App\Models\AuditActivity;
use App\Models\InventoryBatch;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
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

it('records an idempotent physical count reconciliation', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $batch = InventoryBatch::query()->where('inventory_item_id', $item->id)->firstOrFail();
    $payload = ['inventory_store_id' => $store->id, 'count_key' => 'test-cement-count-001', 'reason' => 'Physical count reconciliation.', 'lines' => [[
        'inventory_item_id' => $item->id,
        'inventory_batch_id' => $batch->id,
        'system_quantity' => '1200.0000',
        'counted_quantity' => '1100.0000',
    ]]];

    $this->actingAs($storeKeeper)->post(route('inventory.stock-counts.store'), $payload)->assertRedirect();
    $this->actingAs($storeKeeper)->post(route('inventory.stock-counts.store'), $payload)->assertRedirect();

    expect(InventoryStockMovement::query()->where('source_key', 'stock-count:test-cement-count-001:'.$item->id.':'.$batch->id)->count())->toBe(1)
        ->and(resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe('1100.0000');
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
    $this->actingAs($siteManager)->post(route('inventory.stock-counts.store'), ['inventory_store_id' => $store->id, 'count_key' => 'test-forbidden', 'reason' => 'Must not post.', 'lines' => [[
        'inventory_item_id' => $item->id,
        'inventory_batch_id' => null,
        'system_quantity' => '180.0000',
        'counted_quantity' => '181.0000',
    ]]])->assertForbidden();
});

it('requires an approved purchase order for supplier receipts', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $aggregate = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $before = resolve(InventoryStockBalance::class)->for($store, $aggregate)['on_hand'];

    $this->actingAs($director)->post(route('inventory.receipts.store'), [
        'received_on' => now()->toDateString(),
        'lines' => [[
            'quantity' => '2',
            'accepted_quantity' => '2',
            'rejected_quantity' => '0',
        ]],
    ])->assertSessionHasErrors(['purchase_order_id', 'lines.0.purchase_order_line_id']);

    expect(InventoryGoodsReceipt::query()->count())->toBe(0)
        ->and(resolve(InventoryStockBalance::class)->for($store, $aggregate)['on_hand'])->toBe($before);
});
