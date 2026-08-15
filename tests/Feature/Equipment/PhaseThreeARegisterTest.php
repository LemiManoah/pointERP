<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
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

it('shows the cross-branch fleet register and costs to the fleet manager', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();

    $this->actingAs($fleetManager)
        ->get(route('equipment.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/equipment/index')
            ->has('equipment', 6)
            ->where('can.viewCosts', true)
            ->where('equipment.0.acquisition_amount', fn (mixed $value): bool => $value !== null));
});

it('limits a site manager to branch equipment and removes commercial values', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('equipment.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/equipment/index')
            ->has('equipment', 5)
            ->where('can.viewCosts', false)
            ->where('equipment.0.acquisition_amount', null)
            ->where('equipment.0.hire_rate', null));
});

it('forbids users without equipment permission', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    $this->actingAs($storeKeeper)->get(route('equipment.index'))->assertForbidden();
});

it('creates an equipment category and location through authorised endpoints', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $branch = $director->branches()->where('code', 'KLA-HQ')->firstOrFail();

    $this->actingAs($director)->post(route('equipment-categories.store'), [
        'code' => 'LIFTING',
        'name' => 'Lifting Plant',
        'default_meter_type' => 'engine_hours',
        'default_capacity_unit' => 'tonnes',
        'fuel_efficiency_basis' => 'litres_per_hour',
        'expected_fuel_efficiency' => '12',
        'fuel_tolerance_percent' => '15',
        'is_active' => true,
    ])->assertRedirect(route('equipment.index', ['tab' => 'categories']));

    $this->actingAs($director)->post(route('equipment-locations.store'), [
        'branch_id' => $branch->id,
        'project_id' => null,
        'site_id' => null,
        'type' => 'workshop',
        'code' => 'KLA-WORKSHOP',
        'name' => 'Kampala Workshop',
        'is_active' => true,
    ])->assertRedirect(route('equipment.index', ['tab' => 'locations']));

    expect(EquipmentCategory::query()->where('code', 'LIFTING')->exists())->toBeTrue()
        ->and(EquipmentLocation::query()->where('code', 'KLA-WORKSHOP')->exists())->toBeTrue();
});

it('retires equipment without deleting its historical identity', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $equipment = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();

    $this->actingAs($director)
        ->delete(route('equipment.destroy', $equipment))
        ->assertRedirect(route('equipment.index'));

    expect($equipment->refresh()->is_active)->toBeFalse()
        ->and($equipment->current_status)->toBe('retired')
        ->and(Equipment::withTrashed()->whereKey($equipment->id)->exists())->toBeTrue();
});
