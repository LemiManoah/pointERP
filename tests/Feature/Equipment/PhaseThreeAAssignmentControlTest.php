<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentLocation;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('seeds active and returned custody examples', function (): void {
    $excavator = Equipment::query()->where('asset_code', 'EQ-EXC-003')->firstOrFail();
    $grader = Equipment::query()->where('asset_code', 'EQ-GRD-001')->firstOrFail();

    expect($excavator->assignments()->where('status', EquipmentAssignment::STATUS_ACTIVE)->count())->toBe(1)
        ->and($excavator->current_status)->toBe('assigned')
        ->and($excavator->current_custodian_id)->not->toBeNull()
        ->and($grader->assignments()->where('status', EquipmentAssignment::STATUS_RETURNED)->count())->toBe(1);
});

it('assigns available equipment and derives current custody', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $custodian = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $site = Site::query()->where('project_id', $project->id)->where('reference', 'KIBOGA-HOIMA')->firstOrFail();
    $location = EquipmentLocation::query()->where('site_id', $site->id)->firstOrFail();

    $this->actingAs($manager)->post(route('equipment.assignments.store', $roller), [
        'project_id' => $project->id,
        'site_id' => $site->id,
        'equipment_location_id' => $location->id,
        'custodian_staff_id' => $custodian->staff_id,
        'assigned_at' => now()->subMinute()->toDateTimeString(),
        'expected_return_at' => now()->addMonth()->toDateTimeString(),
        'handover_meter_reading' => '4690.0000',
        'handover_condition' => 'Serviceable after pre-start inspection.',
    ])->assertRedirect(route('equipment.show', ['equipment' => $roller, 'tab' => 'assignments']));

    expect($roller->refresh()->current_status)->toBe('assigned')
        ->and($roller->current_site_id)->toBe($site->id)
        ->and($roller->current_custodian_id)->toBe($custodian->staff_id)
        ->and($roller->current_meter_reading)->toBe('4690.0000');
});

it('returns assigned equipment and clears project custody', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $excavator = Equipment::query()->where('asset_code', 'EQ-EXC-003')->firstOrFail();
    $assignment = $excavator->assignments()->where('status', EquipmentAssignment::STATUS_ACTIVE)->firstOrFail();
    $location = EquipmentLocation::query()->where('branch_id', $excavator->branch_id)->where('type', 'depot')->firstOrFail();

    $this->actingAs($manager)->post(route('equipment-assignments.return', $assignment), [
        'return_location_id' => $location->id,
        'returned_at' => now()->subMinute()->toDateTimeString(),
        'return_meter_reading' => '8410.0000',
        'return_condition' => 'Returned serviceable with no new damage.',
        'return_notes' => 'Work front completed.',
    ])->assertRedirect(route('equipment.show', ['equipment' => $excavator, 'tab' => 'assignments']));

    expect($assignment->refresh()->status)->toBe(EquipmentAssignment::STATUS_RETURNED)
        ->and($excavator->refresh()->current_status)->toBe('available')
        ->and($excavator->current_project_id)->toBeNull()
        ->and($excavator->current_site_id)->toBeNull()
        ->and($excavator->current_custodian_id)->toBeNull();
});

it('forbids users without assignment authority', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();

    $this->actingAs($storeKeeper)->post(route('equipment.assignments.store', $roller), [])->assertForbidden();
});
