<?php

declare(strict_types=1);

use App\Actions\Operations\Equipment\ProcessMaintenanceDueSchedules;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentType;
use App\Models\Equipment;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows the scoped maintenance portfolio to fleet managers', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();

    $this->actingAs($fleetManager)
        ->get(route('equipment.index', ['tab' => 'maintenance']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/equipment/index')
            ->where('activeTab', 'maintenance')
            ->where('can.viewMaintenanceDashboard', true)
            ->where('can.exportMaintenance', true)
            ->has('maintenanceSchedules', 2)
            ->has('maintenanceWorkOrders', 2));
});

it('omits maintenance costs and export authority for site managers', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('equipment.index', ['tab' => 'maintenance']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('can.viewCosts', false)
            ->where('can.viewMaintenanceDashboard', false)
            ->where('can.exportMaintenance', false)
            ->where('maintenanceWorkOrders.0.total_cost', null)
            ->where('maintenanceWorkOrders.0.currency_code', null));
});

it('exports maintenance records only for authorised users', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($fleetManager)
        ->get(route('equipment-maintenance.export', ['search' => 'EQ-GRD-001']))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv')
        ->assertDownload();

    $this->actingAs($siteManager)
        ->get(route('equipment-maintenance.export'))
        ->assertForbidden();
});

it('deduplicates due and overdue maintenance notifications', function (): void {
    Notification::fake();
    $tenantId = User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant_id;
    $asOf = CarbonImmutable::parse('2026-08-16 07:00:00');
    $action = resolve(ProcessMaintenanceDueSchedules::class);

    $first = $action->handle($asOf, $tenantId);
    $second = $action->handle($asOf, $tenantId);

    expect($first['overdue'])->toBeGreaterThan(0)
        ->and($first['notifications'])->toBeGreaterThan(0)
        ->and($second['notifications'])->toBe(0)
        ->and(EquipmentMaintenanceSchedule::query()->whereNotNull('last_notified_at')->exists())->toBeTrue();

    Notification::assertSentTo(
        User::query()->where('email', 'pm.gulu@point.test')->firstOrFail(),
        OperationalNotification::class,
    );
});

it('links controlled evidence to an accessible maintenance work order', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();
    $workOrder = EquipmentMaintenanceWorkOrder::query()->where('reference', 'MWO-GRD-0001')->firstOrFail();
    $documentType = DocumentType::query()->where('code', 'INSPECTION_RECORD')->firstOrFail();
    $document = Document::factory()->create([
        'branch_id' => $workOrder->branch_id,
        'document_type_id' => $documentType->id,
        'owner_id' => $fleetManager->id,
        'title' => 'Grader service completion evidence',
        'created_by' => $fleetManager->id,
    ]);

    $this->actingAs($fleetManager)
        ->post(route('documents.links.store', $document), [
            'type' => 'equipment_maintenance_work_order',
            'id' => $workOrder->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('documents.show', $document));

    expect(DocumentLink::query()
        ->where('document_id', $document->id)
        ->where('linkable_type', EquipmentMaintenanceWorkOrder::class)
        ->where('linkable_id', $workOrder->id)
        ->exists())->toBeTrue();

    $equipment = Equipment::query()->findOrFail($workOrder->equipment_id);
    $workOrderIndex = EquipmentMaintenanceWorkOrder::query()
        ->where('equipment_id', $equipment->id)
        ->orderByDesc('reported_at')
        ->pluck('id')
        ->search($workOrder->id);

    $this->actingAs($fleetManager)
        ->get(route('equipment.show', ['equipment' => $equipment, 'tab' => 'maintenance']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where("maintenanceWorkOrders.{$workOrderIndex}.document_count", 2));
});
