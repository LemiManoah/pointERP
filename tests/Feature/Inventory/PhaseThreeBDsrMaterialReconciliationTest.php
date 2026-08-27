<?php

declare(strict_types=1);

use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Enums\DsrMaterialReconciliationStatus;
use App\Enums\InventoryMovementType;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DsrMaterialReconciliation;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('allocates an existing issue without deducting stock twice', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $line = DailySiteReportMaterialLine::query()->where('delivery_reference', 'DSR-CEMENT-DEMO')->with('report')->firstOrFail();
    $item = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $store = InventoryStore::query()->where('code', 'KLA-MAIN-STORE')->firstOrFail();
    $batch = InventoryBatch::query()->where('inventory_item_id', $item->id)->firstOrFail();

    $movement = resolve(PostInventoryStockMovement::class)->handle($store, $item, [
        'movement_type' => InventoryMovementType::Issue->value,
        'original_quantity' => '10',
        'original_unit_id' => $item->stock_unit_id,
        'inventory_batch_id' => $batch->id,
        'source_key' => 'test:dsr-material:existing-issue',
        'project_id' => $line->report->project_id,
        'site_id' => $line->report->site_id,
        'reason' => 'Previously issued cement for the reported road works.',
    ], $director);
    $movementCount = InventoryStockMovement::query()->count();

    $this->actingAs($director)->post(route('dsr-material-lines.allocate', $line), [
        'inventory_stock_movement_id' => $movement->id,
        'quantity' => '10',
        'reason' => 'Match the existing store issue to the approved DSR.',
    ])->assertRedirect();

    expect(InventoryStockMovement::query()->count())->toBe($movementCount)
        ->and(DsrMaterialReconciliation::query()->where('inventory_stock_movement_id', $movement->id)->value('allocated_quantity'))->toBe('10.0000')
        ->and($line->refresh()->inventory_reconciliation_status)->toBe(DsrMaterialReconciliationStatus::Partial);
});

it('requires explicit permission to classify reported material as external', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $line = DailySiteReportMaterialLine::query()->where('delivery_reference', 'DSR-CEMENT-DEMO')->firstOrFail();

    $this->actingAs($siteManager)->post(route('dsr-material-lines.external', $line), [
        'reason' => 'Supplier delivered directly to site.',
    ])->assertForbidden();
});
