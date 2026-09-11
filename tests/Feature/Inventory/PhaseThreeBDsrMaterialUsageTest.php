<?php

declare(strict_types=1);

use App\Actions\Operations\DailySiteReports\PostApprovedDsrMaterialUsage;
use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Enums\DsrMaterialSource;
use App\Enums\DsrMaterialUsageStatus;
use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStoreType;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportMaterialLine;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStoreItem;
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

it('creates one default site store with a new site', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $project = Project::query()->firstOrFail();

    $this->actingAs($director)->post(route('sites.store'), [
        'project_id' => $project->id,
        'reference' => 'TEST-SITE-STORE',
        'name' => 'Test Site Store',
        'location_name' => 'Kampala',
        'status' => 'active',
    ])->assertRedirect();

    $site = Site::query()->where('reference', 'TEST-SITE-STORE')->firstOrFail();

    expect($site->stores()->where('is_default_for_site', true)->count())->toBe(1)
        ->and($site->defaultStore()->firstOrFail()->type)->toBe(InventoryStoreType::SiteStore);
});

it('posts approved site-store material usage once', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_SUBMITTED)->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $report->materialLines()->delete();
    $report->equipmentLines()->delete();

    $site = Site::query()->findOrFail($report->site_id);
    $store = $site->defaultStore()->firstOrFail();
    InventoryStoreItem::query()->updateOrCreate(
        ['inventory_store_id' => $store->id, 'inventory_item_id' => $item->id],
        [
            'tenant_id' => $report->tenant_id,
            'is_active' => true,
            'created_by' => $director->id,
            'updated_by' => $director->id,
        ],
    );
    $batch = InventoryBatch::query()->create([
        'tenant_id' => $report->tenant_id,
        'inventory_item_id' => $item->id,
        'inventory_store_id' => $store->id,
        'batch_number' => 'TEST-DSR-BATCH',
        'status' => InventoryBatchStatus::Available,
        'is_active' => true,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);
    resolve(PostInventoryStockMovement::class)->handle($store, $item, [
        'movement_type' => InventoryMovementType::OpeningBalance->value,
        'original_quantity' => '50',
        'original_unit_id' => $item->stock_unit_id,
        'inventory_batch_id' => $batch->id,
        'source_key' => 'test:dsr-site-store-opening',
        'project_id' => $report->project_id,
        'site_id' => $report->site_id,
        'reason' => 'Test site-store opening stock.',
    ], $director);

    $line = DailySiteReportMaterialLine::query()->create([
        'tenant_id' => $report->tenant_id,
        'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id,
        'inventory_item_id' => $item->id,
        'inventory_store_id' => $store->id,
        'inventory_batch_id' => $batch->id,
        'unit_of_measure_id' => $item->stock_unit_id,
        'conversion_multiplier' => '1.0000000000',
        'stock_unit_quantity' => '5.0000',
        'material_source' => DsrMaterialSource::SiteStore,
        'material_usage_status' => DsrMaterialUsageStatus::Pending,
        'material_name' => $item->name,
        'quantity' => '5.0000',
        'unit' => $item->stockUnit->symbol ?? $item->stockUnit->name,
        'sort_order' => 0,
    ]);

    $this->actingAs($director)->post(route('daily-site-reports.approve', $report))->assertRedirect();
    resolve(PostApprovedDsrMaterialUsage::class)->handle($report->refresh(), $director);

    expect($line->refresh()->material_usage_status)->toBe(DsrMaterialUsageStatus::Posted)
        ->and($line->inventory_stock_movement_id)->not->toBeNull()
        ->and(InventoryStockMovement::query()->where('source_key', 'dsr-material-usage:'.$line->id)->count())->toBe(1)
        ->and(InventoryStockMovement::query()->where('source_key', 'dsr-material-usage:'.$line->id)->value('quantity'))->toBe('-5.0000');
});

it('records external material usage without changing stock', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_APPROVED)->firstOrFail();
    $line = DailySiteReportMaterialLine::query()->create([
        'tenant_id' => $report->tenant_id,
        'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id,
        'material_source' => DsrMaterialSource::External,
        'material_usage_status' => DsrMaterialUsageStatus::Pending,
        'external_material_reason' => 'The subcontractor supplied and controlled this material.',
        'material_name' => 'Subcontractor formwork',
        'quantity' => '4.0000',
        'unit' => 'piece',
        'sort_order' => 10,
    ]);
    $before = InventoryStockMovement::query()->count();

    resolve(PostApprovedDsrMaterialUsage::class)->handle($report, $director);

    expect($line->refresh()->material_usage_status)->toBe(DsrMaterialUsageStatus::External)
        ->and($line->inventory_stock_movement_id)->toBeNull()
        ->and(InventoryStockMovement::query()->count())->toBe($before);
});
