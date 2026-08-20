<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\EquipmentLocation;
use App\Models\EquipmentTransfer;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('moves equipment only after approval dispatch and destination receipt', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $bowser = Equipment::query()->where('asset_code', 'EQ-WTR-001')->firstOrFail();
    $transfer = $bowser->transfers()->where('status', EquipmentTransfer::STATUS_REQUESTED)->firstOrFail();
    $sourceLocationId = $bowser->current_location_id;

    $this->actingAs($fleetManager)->post(route('equipment-transfers.approve', $transfer))->assertRedirect();
    expect($bowser->refresh()->current_location_id)->toBe($sourceLocationId);

    $this->actingAs($fleetManager)->post(route('equipment-transfers.dispatch', $transfer), [
        'dispatched_at' => now()->subHour()->toDateTimeString(),
        'dispatch_meter_reading' => '186510.0000',
        'dispatch_condition' => 'Serviceable at dispatch.',
        'transport_reference' => 'TRUCK-LOWBED-014',
    ])->assertRedirect();
    expect($bowser->refresh()->current_status)->toBe('transferred')
        ->and($bowser->current_location_id)->toBe($sourceLocationId);

    $this->actingAs($siteManager)->post(route('equipment-transfers.receive', $transfer), [
        'received_at' => now()->toDateTimeString(),
        'receipt_meter_reading' => '186530.0000',
        'receipt_condition' => 'Received serviceable at Busunju.',
    ])->assertRedirect();

    expect($transfer->refresh()->status)->toBe(EquipmentTransfer::STATUS_RECEIVED)
        ->and($bowser->refresh()->current_status)->toBe('available')
        ->and($bowser->current_location_id)->toBe($transfer->destination_location_id)
        ->and($bowser->current_meter_reading)->toBe('186530.0000');
});

it('allows a requester to approve their transfer when they have permission', function (): void {
    $requester = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $transfer = EquipmentTransfer::query()->where('status', EquipmentTransfer::STATUS_REQUESTED)->firstOrFail();

    $this->actingAs($requester)->post(route('equipment-transfers.approve', $transfer))->assertRedirect();
    expect($transfer->refresh()->status)->toBe(EquipmentTransfer::STATUS_APPROVED);
});

it('records a manual location observation without creating a transfer', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();
    $location = EquipmentLocation::query()->where('code', 'KIBOGA-YARD')->firstOrFail();
    $transferCount = $grader->transfers()->count();

    $this->actingAs($siteManager)->post(route('equipment.location-confirmations.store', $grader), [
        'equipment_location_id' => $location->id,
        'observed_at' => now()->subMinute()->toDateTimeString(),
        'observed_status' => 'available',
        'condition_observation' => 'Physically sighted in serviceable condition.',
        'note' => 'Confirmed during plant count.',
    ])->assertRedirect(route('equipment.show', ['equipment' => $grader, 'tab' => 'locations']));

    expect($grader->locationConfirmations()->count())->toBe(2)
        ->and($grader->transfers()->count())->toBe($transferCount)
        ->and($grader->refresh()->current_location_id)->toBe($location->id);
});

it('does not retire equipment with an open transfer', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $bowser = Equipment::query()->where('asset_code', 'EQ-WTR-001')->firstOrFail();

    $this->actingAs($fleetManager)->delete(route('equipment.destroy', $bowser))->assertRedirect();
    expect($bowser->refresh()->is_active)->toBeTrue();
});
