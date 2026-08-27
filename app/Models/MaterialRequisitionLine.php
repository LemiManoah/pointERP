<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $requested_quantity
 * @property-read string $conversion_multiplier
 * @property-read string $stock_quantity
 * @property-read string $approved_quantity
 * @property-read string $issued_quantity
 * @property-read string $returned_quantity
 */
#[Fillable(['tenant_id', 'material_requisition_id', 'inventory_item_id', 'unit_of_measure_id', 'project_activity_id', 'item_code_snapshot', 'item_name_snapshot', 'unit_code_snapshot', 'unit_symbol_snapshot', 'requested_quantity', 'conversion_multiplier', 'stock_quantity', 'approved_quantity', 'issued_quantity', 'returned_quantity', 'purpose', 'notes', 'sort_order'])]
final class MaterialRequisitionLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<MaterialRequisitionLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['requested_quantity' => 'decimal:4', 'conversion_multiplier' => 'decimal:10', 'stock_quantity' => 'decimal:4', 'approved_quantity' => 'decimal:4', 'issued_quantity' => 'decimal:4', 'returned_quantity' => 'decimal:4', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<MaterialRequisition, $this> */
    public function requisition(): BelongsTo { return $this->belongsTo(MaterialRequisition::class, 'material_requisition_id'); }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id'); }

    /** @return BelongsTo<ProjectActivity, $this> */
    public function activity(): BelongsTo { return $this->belongsTo(ProjectActivity::class, 'project_activity_id'); }

    /** @return HasMany<InventoryReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'source_id')->where('source_type', self::class);
    }

    /** @return HasMany<InventoryStockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'source_id')->where('source_type', self::class);
    }
}
