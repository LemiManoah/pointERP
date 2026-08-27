<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DsrMaterialReconciliationType;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $daily_site_report_material_line_id
 * @property-read string|null $inventory_stock_movement_id
 * @property-read string|null $material_requisition_line_id
 * @property-read DsrMaterialReconciliationType $type
 * @property-read string $allocated_quantity
 * @property-read string $source_key
 * @property-read string $reason
 * @property-read CarbonInterface $reconciled_at
 */
#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_material_line_id', 'inventory_stock_movement_id', 'material_requisition_line_id', 'type', 'allocated_quantity', 'source_key', 'reason', 'reconciled_by', 'reconciled_at'])]
final class DsrMaterialReconciliation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DsrMaterialReconciliation>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['type' => DsrMaterialReconciliationType::class, 'allocated_quantity' => 'decimal:4', 'reconciled_at' => 'datetime'];
    }

    /** @return BelongsTo<DailySiteReportMaterialLine, $this> */
    public function materialLine(): BelongsTo
    {
        return $this->belongsTo(DailySiteReportMaterialLine::class, 'daily_site_report_material_line_id');
    }

    /** @return BelongsTo<InventoryStockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryStockMovement::class, 'inventory_stock_movement_id');
    }

    /** @return BelongsTo<MaterialRequisitionLine, $this> */
    public function requisitionLine(): BelongsTo
    {
        return $this->belongsTo(MaterialRequisitionLine::class, 'material_requisition_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
