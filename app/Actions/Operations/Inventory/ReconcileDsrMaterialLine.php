<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\DsrMaterialReconciliationStatus;
use App\Enums\DsrMaterialReconciliationType;
use App\Enums\InventoryMovementType;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DsrMaterialReconciliation;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryStoreStockOptions;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReconcileDsrMaterialLine
{
    public function __construct(private PostInventoryStockMovement $postMovement, private AuditLogger $auditLogger, private InventoryStoreStockOptions $stockOptions) {}

    public function allocate(DailySiteReportMaterialLine $line, InventoryStockMovement $movement, string $quantity, string $reason, User $actor): void
    {
        DB::transaction(function () use ($actor, $line, $movement, $quantity, $reason): void {
            $line = DailySiteReportMaterialLine::query()->lockForUpdate()->findOrFail($line->id);
            $movement = InventoryStockMovement::query()->lockForUpdate()->findOrFail($movement->id);
            $this->assertApprovedAndLinked($line);
            if ($movement->inventory_item_id !== $line->inventory_item_id || $movement->movement_type !== InventoryMovementType::Issue || $movement->project_id !== $line->report->project_id || $movement->site_id !== $line->report->site_id) {
                throw ValidationException::withMessages(['inventory_stock_movement_id' => 'Select an issue for the same item, project and site.']);
            }

            $requested = BigDecimal::of($quantity);
            $movementAvailable = BigDecimal::of((string) $movement->quantity)->abs()->minus((string) DsrMaterialReconciliation::query()->where('inventory_stock_movement_id', $movement->id)->sum('allocated_quantity'));
            if ($requested->isLessThanOrEqualTo(0) || $requested->isGreaterThan($movementAvailable) || $requested->isGreaterThan($this->outstanding($line))) {
                throw ValidationException::withMessages(['quantity' => 'The allocation exceeds the available issue or reported outstanding quantity.']);
            }

            $sourceKey = 'dsr-allocation:'.$line->id.':'.$movement->id.':'.DsrMaterialReconciliation::query()->where('daily_site_report_material_line_id', $line->id)->count();
            DsrMaterialReconciliation::query()->create([
                'tenant_id' => $line->tenant_id, 'branch_id' => $line->branch_id, 'daily_site_report_material_line_id' => $line->id,
                'inventory_stock_movement_id' => $movement->id, 'material_requisition_line_id' => $movement->source_type === MaterialRequisitionLine::class ? $movement->source_id : null,
                'type' => DsrMaterialReconciliationType::RequisitionIssue, 'allocated_quantity' => (string) $requested->toScale(4),
                'source_key' => $sourceKey, 'reason' => $reason, 'reconciled_by' => $actor->id, 'reconciled_at' => now(),
            ]);
            $this->refreshStatus($line, $actor);
            $this->auditLogger->record('inventory.dsr_material.allocated', $line, $actor, [], ['movement_id' => $movement->id, 'quantity' => (string) $requested], $reason, $line->report->branch);
        });
    }

    /** @param array<string, mixed> $data */
    public function directIssue(DailySiteReportMaterialLine $line, array $data, User $actor): void
    {
        DB::transaction(function () use ($actor, $data, $line): void {
            $line = DailySiteReportMaterialLine::query()->lockForUpdate()->findOrFail($line->id);
            $this->assertApprovedAndLinked($line);
            $item = InventoryItem::query()->findOrFail($line->inventory_item_id);
            $store = InventoryStore::query()->where('branch_id', $line->branch_id)->findOrFail($data['inventory_store_id']);
            abort_unless($this->stockOptions->accessibleStoreIds($actor)->contains($store->id), 403);

            $quantity = BigDecimal::of((string) $data['quantity']);
            if ($quantity->isLessThanOrEqualTo(0) || $quantity->isGreaterThan($this->outstanding($line))) {
                throw ValidationException::withMessages(['quantity' => 'Enter a quantity no greater than the reported outstanding quantity.']);
            }

            $sourceKey = 'dsr-direct:'.$line->id.':'.DsrMaterialReconciliation::query()->where('daily_site_report_material_line_id', $line->id)->count();
            $movement = $this->postMovement->handle($store, $item, [
                'movement_type' => InventoryMovementType::Issue->value, 'original_quantity' => (string) $quantity,
                'original_unit_id' => $item->stock_unit_id, 'inventory_batch_id' => $data['inventory_batch_id'] ?? null,
                'source_type' => DailySiteReportMaterialLine::class, 'source_id' => $line->id, 'source_key' => $sourceKey,
                'project_id' => $line->report->project_id, 'site_id' => $line->report->site_id, 'reason' => $data['reason'],
            ], $actor);
            DsrMaterialReconciliation::query()->create([
                'tenant_id' => $line->tenant_id, 'branch_id' => $line->branch_id, 'daily_site_report_material_line_id' => $line->id,
                'inventory_stock_movement_id' => $movement->id, 'type' => DsrMaterialReconciliationType::DirectIssue,
                'allocated_quantity' => (string) $quantity->toScale(4), 'source_key' => $sourceKey,
                'reason' => $data['reason'], 'reconciled_by' => $actor->id, 'reconciled_at' => now(),
            ]);
            $this->refreshStatus($line, $actor);
        });
    }

    public function markExternal(DailySiteReportMaterialLine $line, string $reason, User $actor): void
    {
        DB::transaction(function () use ($actor, $line, $reason): void {
            $line = DailySiteReportMaterialLine::query()->lockForUpdate()->findOrFail($line->id);
            $this->assertApproved($line);
            if ($line->reconciliations()->exists()) {
                throw ValidationException::withMessages(['line' => 'A line with stock allocations cannot be marked external.']);
            }

            $quantity = BigDecimal::of((string) ($line->stock_unit_quantity ?? $line->quantity ?? 0));
            DsrMaterialReconciliation::query()->create([
                'tenant_id' => $line->tenant_id, 'branch_id' => $line->branch_id, 'daily_site_report_material_line_id' => $line->id,
                'type' => DsrMaterialReconciliationType::ExternalNonStock, 'allocated_quantity' => (string) $quantity->toScale(4),
                'source_key' => 'dsr-external:'.$line->id, 'reason' => $reason, 'reconciled_by' => $actor->id, 'reconciled_at' => now(),
            ]);
            $line->forceFill(['inventory_reconciliation_status' => DsrMaterialReconciliationStatus::External, 'external_material_reason' => $reason, 'reconciled_by' => $actor->id, 'reconciled_at' => now()])->save();
            $this->auditLogger->record('inventory.dsr_material.external', $line, $actor, [], ['reason' => $reason], $reason, $line->report->branch);
        });
    }

    private function refreshStatus(DailySiteReportMaterialLine $line, User $actor): void
    {
        $outstanding = $this->outstanding($line);
        $line->forceFill(['inventory_reconciliation_status' => $outstanding->isZero() ? DsrMaterialReconciliationStatus::Reconciled : DsrMaterialReconciliationStatus::Partial, 'reconciled_by' => $actor->id, 'reconciled_at' => $outstanding->isZero() ? now() : null])->save();
    }

    private function outstanding(DailySiteReportMaterialLine $line): BigDecimal
    {
        return BigDecimal::of((string) ($line->stock_unit_quantity ?? 0))->minus((string) $line->reconciliations()->sum('allocated_quantity'));
    }

    private function assertApprovedAndLinked(DailySiteReportMaterialLine $line): void
    {
        $this->assertApproved($line);
        if ($line->inventory_item_id === null || $line->stock_unit_quantity === null) {
            throw ValidationException::withMessages(['line' => 'Link this DSR material to an inventory item before reconciling it.']);
        }
    }

    private function assertApproved(DailySiteReportMaterialLine $line): void
    {
        if (! $line->report->isApproved()) {
            throw ValidationException::withMessages(['line' => 'Only approved DSR material lines can be reconciled.']);
        }
    }
}
