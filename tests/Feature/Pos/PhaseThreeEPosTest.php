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

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows POS only to authorised users', function (): void {
    $cashier = User::query()->where('email', 'cashier.kla@point.test')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

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
