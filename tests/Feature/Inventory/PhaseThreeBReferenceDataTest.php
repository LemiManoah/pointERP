<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows material masters and stores to an authorised quantity user without costs', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    $this->actingAs($storeKeeper)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/inventory/index')
            ->has('items', 3)
            ->has('stores', 1)
            ->where('can.manageItems', false)
            ->where('can.viewCosts', false)
            ->missing('items.0.default_unit_cost')
            ->missing('items.0.default_selling_price'));
});

it('lets a director create an item and store while preserving cost authority', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $category = \App\Models\InventoryCategory::query()->firstOrFail();
    $unit = \App\Models\UnitOfMeasure::query()->where('code', 'BAG')->firstOrFail();
    $branch = $director->branches()->where('code', 'KLA-HQ')->firstOrFail();

    $this->actingAs($director)->post(route('inventory.items.store'), [
        'inventory_category_id' => $category->id,
        'stock_unit_id' => $unit->id,
        'code' => 'SAND-001',
        'name' => 'Fine sand',
        'material_class' => 'construction_material',
        'tracking_type' => 'none',
        'is_expires' => false,
        'is_for_sale' => true,
        'reorder_level' => 10,
        'reorder_quantity' => 50,
        'default_unit_cost' => 85000,
        'default_selling_price' => 100000,
        'is_active' => true,
    ])->assertRedirect(route('inventory.index'));

    $this->actingAs($director)->post(route('inventory.stores.store'), [
        'branch_id' => $branch->id,
        'code' => 'KLA-SECONDARY',
        'name' => 'Kampala Secondary Store',
        'type' => 'warehouse',
        'is_active' => true,
    ])->assertRedirect(route('inventory.index', ['tab' => 'stores']));

    expect(InventoryItem::query()->where('code', 'SAND-001')->value('default_unit_cost'))->toBe('85000.0000')
        ->and(InventoryItem::query()->where('code', 'SAND-001')->value('default_selling_price'))->toBe('100000.0000')
        ->and(InventoryStore::query()->where('code', 'KLA-SECONDARY')->exists())->toBeTrue();
});

it('requires expiry tracking for batch items and can generate a code from the name', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $category = \App\Models\InventoryCategory::query()->firstOrFail();
    $unit = \App\Models\UnitOfMeasure::query()->where('code', 'BAG')->firstOrFail();

    $this->actingAs($director)->post(route('inventory.items.store'), [
        'inventory_category_id' => $category->id,
        'stock_unit_id' => $unit->id,
        'code' => '',
        'name' => 'Tile adhesive',
        'material_class' => 'construction_material',
        'tracking_type' => 'batch',
        'batch_number' => '',
        'is_expires' => false,
        'is_for_sale' => false,
        'reorder_level' => 10,
        'is_active' => true,
    ])->assertSessionHasErrors(['batch_number', 'is_expires']);

    $this->actingAs($director)->post(route('inventory.items.store'), [
        'inventory_category_id' => $category->id,
        'stock_unit_id' => $unit->id,
        'code' => '',
        'name' => 'Tile adhesive',
        'material_class' => 'construction_material',
        'tracking_type' => 'batch',
        'batch_number' => 'TA-2026-08',
        'is_expires' => true,
        'is_for_sale' => false,
        'reorder_level' => 10,
        'is_active' => true,
    ])->assertRedirect(route('inventory.index'));

    expect(InventoryItem::query()->where('code', 'TILE-ADHESIVE')->value('batch_number'))->toBe('TA-2026-08');
});

it('allows a site manager to view quantity setup without cost access', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)->get(route('inventory.index'))->assertOk();
});
