<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DsrMaterialReconciliationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $daily_site_report_id
 * @property-read string|null $inventory_item_id
 * @property-read string|null $inventory_store_id
 * @property-read string|null $unit_of_measure_id
 * @property-read string $material_name
 * @property-read string|null $quantity
 * @property-read string|null $unit
 * @property-read string|null $stock_unit_quantity
 * @property-read DsrMaterialReconciliationStatus $inventory_reconciliation_status
 * @property-read string|null $external_material_reason
 * @property-read CarbonInterface|null $reconciled_at
 * @property-read DailySiteReport $report
 * @property-read InventoryItem|null $item
 * @property-read InventoryStore|null $store
 * @property-read Collection<int, DsrMaterialReconciliation> $reconciliations
 */
#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_id', 'inventory_item_id', 'inventory_store_id', 'unit_of_measure_id', 'conversion_multiplier', 'stock_unit_quantity', 'inventory_reconciliation_status', 'external_material_reason', 'reconciled_by', 'reconciled_at', 'material_name', 'material_type', 'quantity', 'unit', 'rate_amount', 'amount', 'currency_code', 'delivery_reference', 'notes', 'sort_order'])]
final class DailySiteReportMaterialLine extends Model
{
    /** @use HasFactory<Factory<DailySiteReportMaterialLine>> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'conversion_multiplier' => 'decimal:10',
            'stock_unit_quantity' => 'decimal:4',
            'inventory_reconciliation_status' => DsrMaterialReconciliationStatus::class,
            'reconciled_at' => 'datetime',
            'rate_amount' => 'decimal:4',
            'amount' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DailySiteReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class, 'daily_site_report_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function inventoryUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    /** @return HasMany<DsrMaterialReconciliation, $this> */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(DsrMaterialReconciliation::class);
    }
}
