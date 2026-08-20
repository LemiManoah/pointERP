<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('seeds posted and pending fuel control examples', function (): void {
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();
    $excavator = Equipment::query()->where('asset_code', 'EQ-EXC-003')->firstOrFail();

    expect($grader->fuelTransactions()->where('status', EquipmentFuelTransaction::STATUS_POSTED)->count())->toBe(1)
        ->and($excavator->fuelTransactions()->where('status', EquipmentFuelTransaction::STATUS_SUBMITTED)->count())->toBe(1);
});

it('submits fuel and lets a different authorised user post it', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $projectManager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();

    $this->actingAs($siteManager)->post(route('equipment.fuel-transactions.store', $roller), [
        'transacted_at' => now()->subMinute()->toDateTimeString(),
        'transaction_type' => 'issue',
        'fuel_type' => 'diesel',
        'quantity' => '100.0000',
        'source_type' => 'site_stock',
        'source_name' => 'Kiboga site fuel store',
        'meter_reading' => '4690.0000',
        'is_full_tank' => true,
        'voucher_reference' => 'TEST-FUEL-001',
    ])->assertRedirect(route('equipment.show', ['equipment' => $roller, 'tab' => 'fuel']));

    $transaction = EquipmentFuelTransaction::query()->where('voucher_reference', 'TEST-FUEL-001')->firstOrFail();
    expect($transaction->status)->toBe(EquipmentFuelTransaction::STATUS_SUBMITTED)
        ->and($transaction->unit_cost)->toBeNull();

    $this->actingAs($projectManager)->post(route('equipment-fuel-transactions.approve', $transaction))->assertRedirect();

    expect($transaction->refresh()->status)->toBe(EquipmentFuelTransaction::STATUS_POSTED)
        ->and($transaction->approved_by)->toBe($projectManager->id)
        ->and($roller->refresh()->current_meter_reading)->toBe('4690.0000');
});

it('allows the submitter to approve fuel when they have permission', function (): void {
    $projectManager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();

    $this->actingAs($projectManager)->post(route('equipment.fuel-transactions.store', $roller), [
        'transacted_at' => now()->subMinute()->toDateTimeString(),
        'transaction_type' => 'issue',
        'fuel_type' => 'diesel',
        'quantity' => '40.0000',
        'source_type' => 'store',
        'source_name' => 'Gulu plant depot',
        'is_full_tank' => false,
        'voucher_reference' => 'TEST-FUEL-SELF',
    ])->assertRedirect();

    $transaction = EquipmentFuelTransaction::query()->where('voucher_reference', 'TEST-FUEL-SELF')->firstOrFail();
    $this->actingAs($projectManager)->post(route('equipment-fuel-transactions.approve', $transaction))->assertRedirect();

    expect($transaction->refresh()->status)->toBe(EquipmentFuelTransaction::STATUS_POSTED);
});

it('does not accept costs from users without cost authority', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();

    $this->actingAs($siteManager)->post(route('equipment.fuel-transactions.store', $roller), [
        'transacted_at' => now()->subMinute()->toDateTimeString(),
        'transaction_type' => 'issue',
        'fuel_type' => 'diesel',
        'quantity' => '30.0000',
        'source_type' => 'store',
        'source_name' => 'Gulu plant depot',
        'unit_cost' => '5300.0000',
        'currency_code' => 'UGX',
        'is_full_tank' => false,
        'voucher_reference' => 'TEST-FUEL-COST',
    ])->assertSessionHasErrors('unit_cost');

    expect(EquipmentFuelTransaction::query()->where('voucher_reference', 'TEST-FUEL-COST')->exists())->toBeFalse();
});

it('reverses a posted entry with an additive negative transaction', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $original = EquipmentFuelTransaction::query()->where('voucher_reference', 'FUEL-ISSUE-2407')->firstOrFail();

    $this->actingAs($fleetManager)->post(route('equipment-fuel-transactions.reverse', $original), [
        'reason' => 'The supplier delivery note was allocated to the wrong asset.',
    ])->assertRedirect();

    $reversal = EquipmentFuelTransaction::query()->where('reversal_of_id', $original->id)->firstOrFail();
    expect($original->refresh()->status)->toBe(EquipmentFuelTransaction::STATUS_REVERSED)
        ->and($reversal->status)->toBe(EquipmentFuelTransaction::STATUS_POSTED)
        ->and($reversal->quantity)->toBe('-180.0000')
        ->and($reversal->total_cost)->toBe('-945000.0000');
});
