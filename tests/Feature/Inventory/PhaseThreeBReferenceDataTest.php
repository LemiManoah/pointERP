<?php

declare(strict_types=1);

use App\Models\AuditActivity;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
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
    $itemCount = InventoryItem::query()->count();

    $this->actingAs($storeKeeper)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/inventory/index')
            ->has('items', $itemCount)
            ->has('stores', 1)
            ->where('can.manageItems', false)
            ->where('can.viewCosts', false)
            ->missing('items.0.default_unit_cost')
            ->missing('items.0.default_selling_price'));
});

it('lets a director create an item and store while preserving cost authority', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $category = InventoryCategory::query()->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'BAG')->firstOrFail();
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
        'minimum_stock' => 10,
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
    $category = InventoryCategory::query()->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'BAG')->firstOrFail();

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
        'minimum_stock' => 10,
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
        'minimum_stock' => 10,
        'is_active' => true,
    ])->assertRedirect(route('inventory.index'));

    expect(InventoryItem::query()->where('code', 'TILE-ADHESIVE')->value('batch_number'))->toBe('TA-2026-08');
});

it('allows a site manager to view quantity setup without cost access', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)->get(route('inventory.index'))->assertOk();
});

it('shows the seeded item reference details and hides price lists without cost permission', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $cement = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();

    $this->actingAs($director)
        ->get(route('inventory.items.show', $cement))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/inventory/show')
            ->has('conversions', 1)
            ->has('prices', 2)
            ->has('batches', 1)
            ->has('storeSettings', 2)
            ->where('can.manage', true)
            ->where('can.viewCosts', true));

    $this->actingAs($storeKeeper)
        ->get(route('inventory.items.show', $cement))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('prices', 0)
            ->where('can.manage', false)
            ->where('can.viewCosts', false));
});

it('creates reusable price lists before attaching an item price', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'AGG-20')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'TONNE')->firstOrFail();
    $branch = $director->branches()->where('code', 'KLA-HQ')->firstOrFail();

    $this->actingAs($director)->post(route('inventory.price-lists.store'), [
        'code' => 'CONTRACTOR', 'name' => 'Contractor', 'description' => 'Approved contractor selling prices.', 'priority' => 75, 'is_active' => true,
    ])->assertRedirect(route('inventory.index', ['tab' => 'price-lists']));
    $priceList = InventoryPriceTier::query()->where('code', 'CONTRACTOR')->firstOrFail();

    $this->actingAs($director)->post(route('inventory.items.prices.store', $item), [
        'inventory_price_tier_id' => $priceList->id, 'branch_id' => $branch->id,
        'unit_of_measure_id' => $unit->id, 'amount' => '140000', 'minimum_quantity' => '10', 'is_active' => true,
    ])->assertRedirect(route('inventory.items.show', $item));

    expect($priceList->prices()->where('inventory_item_id', $item->id)->value('amount'))->toBe('140000.0000');
});

it('authorises inventory mutations by permission and audits permanent deletion', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'PPE-VEST')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'KG')->firstOrFail();

    $this->actingAs($siteManager)->post(route('inventory.items.conversions.store', $item), [
        'from_unit_id' => $unit->id,
        'multiplier' => 1,
        'is_active' => true,
    ])->assertForbidden();

    $unused = InventoryItem::query()->create([
        'tenant_id' => $director->tenant_id,
        'inventory_category_id' => $item->inventory_category_id,
        'stock_unit_id' => $item->stock_unit_id,
        'code' => 'DELETE-ME',
        'name' => 'Unused test item',
        'material_class' => 'other',
        'tracking_type' => 'none',
        'is_expires' => false,
        'is_for_sale' => false,
        'minimum_stock' => 0,
        'is_active' => false,
        'created_by' => $siteManager->id,
    ]);

    $this->actingAs($director)
        ->delete(route('inventory.items.force-destroy', $unused))
        ->assertRedirect(route('inventory.index', ['status' => 'inactive']));

    expect(InventoryItem::withTrashed()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(AuditActivity::query()->where('event', 'inventory.item.permanently_deleted')->exists())->toBeTrue();
});
