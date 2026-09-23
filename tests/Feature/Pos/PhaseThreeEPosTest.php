<?php

declare(strict_types=1);

use App\Enums\PosSalePaymentStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\User;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    resolve(TenantContext::class)->set($director->tenant);
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $cashier = User::factory()->withoutTwoFactor()->create(['tenant_id' => $director->tenant_id, 'email' => 'cashier.kla@point.test']);
    $cashier->assignRole('Cashier');
    $cashier->branches()->attach($store->branch_id, ['is_default' => true]);
    $this->withoutVite();
});

it('shows POS only to authorised users', function (): void {
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $siteManager = User::factory()->withoutTwoFactor()->create(['tenant_id' => $cashier->tenant_id]);
    $siteManager->assignRole('Site Manager');

    $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
    $this->actingAs($siteManager)->get(route('pos.index'))->assertForbidden();
});

it('completes a paid sale and posts the matching stock issue', function (): void {
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $tier = InventoryPriceTier::query()->where('code', 'RETAIL')->firstOrFail();
    $before = (float) resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'];

    $payload = [
        'checkout_key' => fake()->uuid(),
        'branch_id' => $store->branch_id,
        'inventory_store_id' => $store->id,
        'inventory_price_tier_id' => $tier->id,
        'lines' => [[
            'inventory_item_id' => $item->id,
            'unit_of_measure_id' => $item->stock_unit_id,
            'quantity' => '2',
            'discount_amount' => '0',
        ]],
        'payments' => [['method' => 'cash', 'amount' => '96000', 'reference' => null]],
    ];
    $this->actingAs($cashier)->post(route('pos.store'), $payload)->assertRedirect();
    $this->actingAs($cashier)->post(route('pos.store'), $payload)->assertRedirect();

    $sale = PosSale::query()->latest()->firstOrFail();
    expect($sale->status->value)->toBe('completed')
        ->and($sale->total_amount)->toBe('96000.0000')
        ->and($sale->amount_paid)->toBe('96000.0000')
        ->and($sale->balance_due)->toBe('0.0000')
        ->and($sale->payment_status)->toBe(PosSalePaymentStatus::Paid)
        ->and(PosPayment::query()->where('pos_sale_id', $sale->id)->value('amount'))->toBe('96000.0000')
        ->and(InventoryStockMovement::query()->where('source_key', 'like', 'pos-sale:%')->count())->toBe(1)
        ->and(PosSale::query()->count())->toBe(1)
        ->and((float) resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe($before - 2);
});

it('allows authorised credit sales and records later payments without moving stock twice', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $customer = Customer::query()->where('type', Customer::TYPE_CLIENT)->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $tier = InventoryPriceTier::query()->where('code', 'RETAIL')->firstOrFail();
    $payload = [
        'checkout_key' => fake()->uuid(),
        'branch_id' => $store->branch_id,
        'inventory_store_id' => $store->id,
        'inventory_price_tier_id' => $tier->id,
        'customer_id' => $customer->id,
        'lines' => [[
            'inventory_item_id' => $item->id,
            'unit_of_measure_id' => $item->stock_unit_id,
            'quantity' => '1',
            'discount_amount' => '0',
        ]],
        'payments' => [['method' => 'cash', 'amount' => '20000', 'reference' => null]],
    ];

    $this->actingAs($cashier)->post(route('pos.store'), $payload)->assertSessionHasErrors('payments');
    $this->actingAs($director)->post(route('pos.store'), $payload)->assertRedirect();

    $sale = PosSale::query()->latest()->firstOrFail();
    $movementCount = InventoryStockMovement::query()->where('source_key', 'like', 'pos-sale:%')->count();
    expect($sale->payment_status)->toBe(PosSalePaymentStatus::PartiallyPaid)
        ->and($sale->amount_paid)->toBe('20000.0000')
        ->and($sale->balance_due)->toBe('28000.0000');

    $this->actingAs($director)->post(route('pos.payments.store', $sale), [
        'method' => 'cash',
        'amount' => '30000',
    ])->assertSessionHasErrors('amount');

    $this->actingAs($director)->post(route('pos.payments.store', $sale), [
        'method' => 'mobile_money',
        'amount' => '28000',
        'reference' => 'MM-TEST-001',
    ])->assertRedirect(route('pos.show', $sale));

    expect($sale->refresh()->payment_status)->toBe(PosSalePaymentStatus::Paid)
        ->and($sale->amount_paid)->toBe('48000.0000')
        ->and($sale->balance_due)->toBe('0.0000')
        ->and($sale->payments()->count())->toBe(2)
        ->and(InventoryStockMovement::query()->where('source_key', 'like', 'pos-sale:%')->count())->toBe($movementCount);
});

it('rejects a sale that would create negative stock', function (): void {
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $tier = InventoryPriceTier::query()->where('code', 'RETAIL')->firstOrFail();

    $this->actingAs($cashier)->post(route('pos.store'), [
        'checkout_key' => fake()->uuid(),
        'branch_id' => $store->branch_id,
        'inventory_store_id' => $store->id,
        'inventory_price_tier_id' => $tier->id,
        'lines' => [[
            'inventory_item_id' => $item->id,
            'unit_of_measure_id' => $item->stock_unit_id,
            'quantity' => '999999',
            'discount_amount' => '0',
        ]],
        'payments' => [['method' => 'cash', 'amount' => '47999952000', 'reference' => null]],
    ])->assertSessionHasErrors('lines');

    expect(PosSale::query()->count())->toBe(0);
});

it('keeps checkout separate from stock posting and restores the cart for editing', function (): void {
    $this->withoutVite();
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $tier = InventoryPriceTier::query()->where('code', 'RETAIL')->firstOrFail();
    $payload = [
        'checkout_key' => fake()->uuid(), 'branch_id' => $store->branch_id,
        'inventory_store_id' => $store->id, 'inventory_price_tier_id' => $tier->id,
        'lines' => [['inventory_item_id' => $item->id, 'unit_of_measure_id' => $item->stock_unit_id, 'quantity' => '1', 'discount_amount' => '0']],
        'payments' => [],
    ];
    $before = resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'];
    $url = route('pos.checkout', ['cart' => $payload['checkout_key']]);
    $this->actingAs($cashier)->post(route('pos.checkout.prepare'), $payload)->assertSessionHasNoErrors()->assertRedirect($url);
    expect(PosSale::query()->count())->toBe(0)
        ->and(resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe($before);
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('operations/pos/checkout')->where('checkoutKey', $payload['checkout_key'])->where('draft.lines.0.quantity', '1'));
    $this->get(route('pos.index', ['cart' => $payload['checkout_key']]))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('operations/pos/index')->where('draft.lines.0.inventory_item_id', $item->id));
    $this->post(route('pos.store'), [...$payload, 'payments' => [['method' => 'cash', 'amount' => '48000']]])->assertSessionHasNoErrors()->assertRedirect();
    expect(PosSale::query()->count())->toBe(1);
    $this->get($url)->assertRedirect(route('pos.index'));
});

it('guards checkout access and does not reveal another users session cart', function (): void {
    $this->withoutVite();
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $key = fake()->uuid();
    $this->withSession(['pos_carts.'.$director->tenant_id.'.'.$director->id.'.'.$key => ['checkout_key' => $key]])
        ->actingAs($cashier)->get(route('pos.checkout', ['cart' => $key]))->assertRedirect(route('pos.index'));
    $cashier->syncRoles([]);
    $cashier->syncPermissions(['pos.view']);
    $this->get(route('pos.checkout'))->assertForbidden();
    $this->post(route('pos.checkout.prepare'), [])->assertForbidden();
});


it('sells shared batches using stock movements rather than batch store metadata', function (): void {
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $batch = \App\Models\InventoryBatch::query()->where('inventory_item_id', $item->id)->firstOrFail();
    $batch->update(['inventory_store_id' => null, 'expires_on' => now()->addDays(10)]);
    $balances = resolve(InventoryStockBalance::class);
    expect($balances->availableForPos($store, $item))->toBe($balances->for($store, $item)['available']);
    $this->actingAs($cashier)->post(route('pos.store'), [
        'checkout_key' => fake()->uuid(), 'branch_id' => $store->branch_id, 'inventory_store_id' => $store->id,
        'inventory_price_tier_id' => InventoryPriceTier::query()->where('code', 'RETAIL')->firstOrFail()->id,
        'lines' => [['inventory_item_id' => $item->id, 'unit_of_measure_id' => $item->stock_unit_id, 'quantity' => '1', 'discount_amount' => '0']],
        'payments' => [['method' => 'cash', 'amount' => '48000']],
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect(PosSale::query()->firstOrFail()->lines()->firstOrFail()->allocations()->firstOrFail()->inventory_batch_id)->toBe($batch->id);
});

it('excludes expired and blocked batches from displayed POS availability', function (): void {
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $batches = \App\Models\InventoryBatch::query()->where('inventory_item_id', $item->id);
    $batches->update(['expires_on' => now()->subDay()]);
    expect(resolve(InventoryStockBalance::class)->availableForPos($store, $item))->toBe('0.0000');
    $batches->update(['expires_on' => now()->addMonth(), 'is_active' => false]);
    expect(resolve(InventoryStockBalance::class)->availableForPos($store, $item))->toBe('0.0000');
});

it('uses stock location even when batch metadata names another store', function (): void {
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $otherStore = $store->replicate();
    $otherStore->fill(['code' => 'POS-EMPTY-STORE', 'name' => 'Empty POS test store', 'site_id' => null, 'is_default_for_site' => false]);
    $otherStore->save();
    $batch = \App\Models\InventoryBatch::query()->where('inventory_item_id', $item->id)->firstOrFail();
    $batch->update(['inventory_store_id' => $otherStore->id, 'expires_on' => now()->addMonth()]);
    $balances = resolve(InventoryStockBalance::class);
    expect($balances->sellableBatches($store, $item)->modelKeys())->toContain($batch->id)
        ->and($balances->availableForPos($store, $item))->toBe($balances->for($store, $item)['available'])
        ->and($balances->sellableBatches($otherStore, $item))->toHaveCount(0)
        ->and($balances->availableForPos($otherStore, $item))->toBe('0.0000');
});
