<?php

declare(strict_types=1);

use App\Enums\InventoryReservationStatus;
use App\Enums\MaterialRequisitionStatus;
use App\Models\InventoryItem;
use App\Models\InventoryReservation;
use App\Models\InventoryStockMovement;
use App\Models\MaterialRequisition;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('lets an authorised project manager approve a submitted branch requisition', function (): void {
    $projectManager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $requisition = MaterialRequisition::query()->where('reference', 'MR-DEMO-GULU')->firstOrFail();

    $this->actingAs($projectManager)->post(route('inventory.requisitions.review', $requisition), [
        'decision' => 'approve',
        'reason' => 'Required for the planned drainage pour.',
    ])->assertRedirect(route('inventory.requisitions.show', $requisition));

    $line = $requisition->lines()->firstOrFail();
    expect($requisition->refresh()->status)->toBe(MaterialRequisitionStatus::Approved)
        ->and($line->refresh()->approved_quantity)->toBe('80.0000')
        ->and(InventoryReservation::query()->where('source_id', $line->id)->value('reserved_quantity'))->toBe('80.0000');
});

it('partially issues and fulfils an approved requisition without losing its reservation history', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $requisition = MaterialRequisition::query()->where('reference', 'MR-DEMO-KLA')->firstOrFail();
    $line = $requisition->lines()->firstOrFail();
    $store = $requisition->store;
    $item = InventoryItem::query()->where('code', 'PPE-VEST')->firstOrFail();

    $this->actingAs($storeKeeper)->post(route('inventory.requisitions.lines.issue', [$requisition, $line]), [
        'quantity' => '10',
        'reason' => 'First crew collected ten vests.',
        'source_key' => (string) Str::uuid(),
    ])->assertRedirect(route('inventory.requisitions.show', $requisition));

    expect($requisition->refresh()->status)->toBe(MaterialRequisitionStatus::PartiallyIssued)
        ->and($line->refresh()->issued_quantity)->toBe('10.0000')
        ->and(resolve(InventoryStockBalance::class)->for($store, $item)['on_hand'])->toBe('65.0000');

    $this->actingAs($storeKeeper)->post(route('inventory.requisitions.lines.issue', [$requisition, $line]), [
        'quantity' => '15',
        'reason' => 'Remaining crew collected the balance.',
        'source_key' => (string) Str::uuid(),
    ])->assertRedirect(route('inventory.requisitions.show', $requisition));

    $reservation = InventoryReservation::query()->where('source_id', $line->id)->firstOrFail();
    expect($requisition->refresh()->status)->toBe(MaterialRequisitionStatus::Fulfilled)
        ->and($line->refresh()->issued_quantity)->toBe('25.0000')
        ->and($reservation->status)->toBe(InventoryReservationStatus::Fulfilled)
        ->and(InventoryStockMovement::query()->where('source_id', $line->id)->count())->toBe(2);
});

it('records unused material returns without rewriting the original issues', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();
    $requisition = MaterialRequisition::query()->where('reference', 'MR-DEMO-KLA')->firstOrFail();
    $line = $requisition->lines()->firstOrFail();

    $this->actingAs($storeKeeper)->post(route('inventory.requisitions.lines.issue', [$requisition, $line]), ['quantity' => '25', 'reason' => 'Crew issue.', 'source_key' => (string) Str::uuid()])->assertRedirect();
    $this->actingAs($director)->post(route('inventory.requisitions.lines.return', [$requisition, $line]), ['quantity' => '5', 'reason' => 'Five unused vests returned.', 'source_key' => (string) Str::uuid()])->assertRedirect();

    expect($line->refresh()->returned_quantity)->toBe('5.0000')
        ->and($line->stockMovements()->count())->toBe(2)
        ->and($line->stockMovements()->where('quantity', '>', 0)->exists())->toBeTrue();
});

it('forbids users without issue authority and users outside the requisition branch', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $requisition = MaterialRequisition::query()->where('reference', 'MR-DEMO-KLA')->firstOrFail();
    $line = $requisition->lines()->firstOrFail();

    $this->actingAs($siteManager)->post(route('inventory.requisitions.lines.issue', [$requisition, $line]), ['quantity' => '1', 'reason' => 'Must not issue.', 'source_key' => (string) Str::uuid()])->assertForbidden();
});

it('creates and submits a material requisition through the user interface endpoints', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $demo = MaterialRequisition::query()->where('reference', 'MR-DEMO-GULU')->with(['store', 'project', 'site'])->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'BAG')->firstOrFail();

    $this->actingAs($siteManager)->post(route('inventory.requisitions.store'), [
        'branch_id' => $demo->branch_id,
        'inventory_store_id' => $demo->inventory_store_id,
        'project_id' => $demo->project_id,
        'site_id' => $demo->site_id,
        'required_by_date' => now()->addWeek()->toDateString(),
        'priority' => 'normal',
        'reason' => 'Cement for the next approved work section.',
        'lines' => [['inventory_item_id' => $item->id, 'unit_of_measure_id' => $unit->id, 'requested_quantity' => '20', 'purpose' => 'Concrete works']],
    ])->assertRedirect();

    $created = MaterialRequisition::query()->where('reason', 'Cement for the next approved work section.')->firstOrFail();
    $this->actingAs($siteManager)->post(route('inventory.requisitions.submit', $created))->assertRedirect(route('inventory.requisitions.show', $created));

    expect($created->refresh()->status)->toBe(MaterialRequisitionStatus::Submitted)
        ->and($created->lines)->toHaveCount(1);
});
