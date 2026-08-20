<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('seeds an accepted opening ledger and calculated usage', function (): void {
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();
    $readings = $grader->meterReadings()->where('status', EquipmentMeterReading::STATUS_ACCEPTED)->oldest('read_at')->get();

    expect($readings)->toHaveCount(2)
        ->and($readings->first()?->event_type)->toBe('opening')
        ->and($readings->last()?->usage)->toBe('120.0000')
        ->and($grader->current_meter_reading)->toBe($readings->last()?->reading_value);
});

it('records a monotonic reading and updates the equipment cache', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();

    $this->actingAs($siteManager)->post(route('equipment.meter-readings.store', $grader), [
        'reading_value' => '12600.0000',
        'read_at' => '2026-08-01 08:00:00',
        'evidence_note' => 'Verified against the signed operator log.',
    ])->assertRedirect(route('equipment.show', ['equipment' => $grader, 'tab' => 'readings']));

    $latest = $grader->meterReadings()->where('status', EquipmentMeterReading::STATUS_ACCEPTED)->latest('read_at')->firstOrFail();
    expect($latest->usage)->toBe('30.0000')
        ->and($grader->refresh()->current_meter_reading)->toBe('12600.0000');
});

it('blocks a lower normal reading and readings on meterless equipment', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();

    $this->actingAs($siteManager)->post(route('equipment.meter-readings.store', $grader), [
        'reading_value' => '12000',
        'read_at' => '2026-08-01 08:00:00',
    ])->assertSessionHasErrors('reading_value');

    $grader->forceFill(['meter_type' => 'none'])->save();
    $this->actingAs($siteManager)->post(route('equipment.meter-readings.store', $grader), [
        'reading_value' => '12600',
        'read_at' => '2026-08-01 08:00:00',
    ])->assertSessionHasErrors('reading_value');
});

it('preserves the original while another user approves a correction', function (): void {
    $requester = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $approver = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();
    $target = $grader->meterReadings()->where('event_type', 'manual')->firstOrFail();

    $this->actingAs($requester)->post(route('equipment-meter-readings.corrections.store', $target), [
        'reading_value' => '12565.0000',
        'reason' => 'The operator transposed the final digit in the signed logbook.',
        'evidence_note' => 'Meter photograph checked by the site engineer.',
    ])->assertRedirect();

    $correction = $target->corrections()->where('status', EquipmentMeterReading::STATUS_PENDING)->firstOrFail();
    $this->actingAs($approver)->post(route('equipment-meter-readings.approve', $correction), [
        'decision_note' => 'Logbook and photograph agree.',
    ])->assertRedirect();

    expect($target->refresh()->status)->toBe(EquipmentMeterReading::STATUS_SUPERSEDED)
        ->and($correction->refresh()->status)->toBe(EquipmentMeterReading::STATUS_ACCEPTED)
        ->and($correction->usage)->toBe('115.0000')
        ->and($grader->refresh()->current_meter_reading)->toBe('12565.0000');
});

it('allows correction self approval when the user has approval permission', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $target = $roller->meterReadings()->where('event_type', 'opening')->firstOrFail();

    $this->actingAs($manager)->post(route('equipment-meter-readings.corrections.store', $target), [
        'reading_value' => $target->reading_value,
        'reason' => 'Opening register evidence needs an administrative confirmation.',
    ])->assertRedirect();
    $correction = $target->corrections()->where('status', EquipmentMeterReading::STATUS_PENDING)->firstOrFail();

    $this->actingAs($manager)->post(route('equipment-meter-readings.approve', $correction))->assertRedirect();
    expect($correction->refresh()->status)->toBe(EquipmentMeterReading::STATUS_ACCEPTED);
});

it('forbids users without meter permissions', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();

    $this->actingAs($storeKeeper)->post(route('equipment.meter-readings.store', $grader), [
        'reading_value' => '12600',
        'read_at' => '2026-08-01 08:00:00',
    ])->assertForbidden();
});
