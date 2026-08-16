<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\EquipmentMaintenanceWorkOrder;
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

it('shows calculated due state and seeded maintenance history', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();

    $this->actingAs($fleetManager)
        ->get(route('equipment.show', ['equipment' => $roller, 'tab' => 'maintenance']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('activeTab', 'maintenance')
            ->where('maintenanceSchedules.0.due_status', 'overdue')
            ->where('can.manageMaintenance', true));
});

it('controls the maintenance lifecycle and equipment availability', function (): void {
    $projectManager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $excavator = Equipment::query()->where('asset_code', 'EQ-EXC-003')->firstOrFail();

    $this->actingAs($projectManager)->post(route('equipment.maintenance-schedules.store', $excavator), [
        'maintenance_type' => 'preventive_service', 'name' => 'Test 250-hour service',
        'basis' => 'meter', 'interval_meter_units' => '250.0000',
        'last_service_reading' => $excavator->current_meter_reading,
        'warning_days' => 14, 'warning_meter_units' => '25.0000',
        'responsible_user_id' => $projectManager->id, 'is_active' => true,
    ])->assertRedirect();
    $schedule = EquipmentMaintenanceSchedule::query()->where('name', 'Test 250-hour service')->firstOrFail();

    $this->actingAs($projectManager)->post(route('equipment.maintenance-work-orders.store', $excavator), [
        'equipment_maintenance_schedule_id' => $schedule->id,
        'reference' => 'MWO-TEST-001', 'maintenance_type' => 'preventive_service',
        'priority' => 'high', 'description' => 'Perform controlled test service.',
        'reported_at' => now()->subHour()->toDateTimeString(),
    ])->assertRedirect();
    $workOrder = EquipmentMaintenanceWorkOrder::query()->where('reference', 'MWO-TEST-001')->firstOrFail();

    $this->actingAs($projectManager)->post(route('equipment-maintenance-work-orders.approve', $workOrder))->assertForbidden();
    $this->actingAs($fleetManager)->post(route('equipment-maintenance-work-orders.approve', $workOrder))->assertRedirect();
    $this->actingAs($fleetManager)->post(route('equipment-maintenance-work-orders.start', $workOrder), [
        'actual_start_at' => now()->subMinutes(30)->toDateTimeString(),
        'opening_meter_reading' => $excavator->current_meter_reading,
    ])->assertRedirect();
    expect($excavator->refresh()->current_status)->toBe('under_maintenance');

    $this->actingAs($fleetManager)->post(route('equipment-maintenance-work-orders.complete', $workOrder), [
        'completed_at' => now()->toDateTimeString(), 'closing_meter_reading' => '8400.0000',
        'downtime_hours' => '0.5000', 'work_performed' => 'Changed filters and completed inspection.',
        'labour_cost' => '200000.0000', 'other_cost' => '50000.0000', 'currency_code' => 'UGX',
        'parts' => [[
            'part_code' => 'TEST-FLT', 'part_name' => 'Test filter', 'quantity' => '2.0000',
            'unit' => 'piece', 'unit_cost' => '100000.0000',
        ]],
    ])->assertRedirect();

    expect($workOrder->refresh()->status)->toBe(EquipmentMaintenanceWorkOrder::STATUS_COMPLETED)
        ->and($workOrder->total_cost)->toBe('450000.0000')
        ->and($workOrder->parts)->toHaveCount(1)
        ->and($excavator->refresh()->current_status)->toBe('assigned')
        ->and($excavator->current_meter_reading)->toBe('8400.0000')
        ->and($schedule->refresh()->next_due_reading)->toBe('8650.0000');
});

it('does not expose maintenance costs to site managers', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('equipment.show', ['equipment' => $grader, 'tab' => 'maintenance']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('maintenanceWorkOrders.0.total_cost', null)
            ->where('maintenanceWorkOrders.0.parts.0.unit_cost', null));
});

it('forbids schedule management without maintenance authority', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();

    $this->actingAs($siteManager)
        ->post(route('equipment.maintenance-schedules.store', $roller), [])
        ->assertForbidden();
});
