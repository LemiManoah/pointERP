<?php

declare(strict_types=1);

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryTransfer;
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

it('records a multi-store transfer atomically after approval', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $source = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $destination = InventoryStore::query()->where('code', 'GUL-SITE-STORE')->firstOrFail();
    $cement = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $batch = InventoryBatch::query()->where('inventory_item_id', $cement->id)->firstOrFail();

    $this->actingAs($director)->post(route('inventory.transfers.store'), [
        'source_store_id' => $source->id,
        'destination_store_id' => $destination->id,
        'transfer_key' => 'test-transfer-001',
        'reason' => 'Move cement to the road site.',
        'lines' => [[
            'inventory_item_id' => $cement->id,
            'unit_of_measure_id' => $cement->stock_unit_id,
            'inventory_batch_id' => $batch->id,
            'quantity' => '20',
        ]],
    ])->assertRedirect(route('inventory.transfers.index'));

    $transfer = InventoryTransfer::query()->where('request_key', 'test-transfer-001')->firstOrFail();

    expect(resolve(InventoryStockBalance::class)->for($source, $cement)['on_hand'])->toBe('1200.0000')
        ->and(resolve(InventoryStockBalance::class)->for($destination, $cement)['on_hand'])->toBe('0.0000');

    $this->actingAs($director)->post(route('inventory.transfers.approve', $transfer))->assertRedirect();

    expect(resolve(InventoryStockBalance::class)->for($source, $cement)['on_hand'])->toBe('1180.0000')
        ->and(resolve(InventoryStockBalance::class)->for($destination, $cement)['on_hand'])->toBe('20.0000')
        ->and(InventoryStockMovement::query()->where('source_id', $transfer->id)->count())->toBe(2);
});

it('rejects a transfer that exceeds the selected batch balance', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $source = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $destination = InventoryStore::query()->where('code', 'GUL-SITE-STORE')->firstOrFail();
    $cement = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $batch = InventoryBatch::query()->where('inventory_item_id', $cement->id)->firstOrFail();

    $this->actingAs($director)->post(route('inventory.transfers.store'), [
        'source_store_id' => $source->id,
        'destination_store_id' => $destination->id,
        'transfer_key' => 'test-transfer-too-large',
        'reason' => 'This must be rejected.',
        'lines' => [[
            'inventory_item_id' => $cement->id,
            'unit_of_measure_id' => $cement->stock_unit_id,
            'inventory_batch_id' => $batch->id,
            'quantity' => '2000',
        ]],
    ])->assertRedirect(route('inventory.transfers.index'));

    $transfer = InventoryTransfer::query()->where('request_key', 'test-transfer-too-large')->firstOrFail();
    $this->actingAs($director)->post(route('inventory.transfers.approve', $transfer))->assertSessionHasErrors();

    expect(resolve(InventoryStockBalance::class)->for($source, $cement)['on_hand'])->toBe('1200.0000')
        ->and(resolve(InventoryStockBalance::class)->for($destination, $cement)['on_hand'])->toBe('0.0000');
});

it('forbids users without stock authority from transfer and count pages', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)->get(route('inventory.transfers.index'))->assertForbidden();
    $this->actingAs($siteManager)->get(route('inventory.stock-counts.index'))->assertForbidden();
});
