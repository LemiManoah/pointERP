<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstimateResourceType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $work_item_template_id
 * @property-read EstimateResourceType $resource_type
 * @property-read string|null $inventory_item_id
 * @property-read string|null $unit_of_measure_id
 * @property-read string|null $equipment_category_id
 * @property-read string|null $workforce_trade_id
 * @property-read string|null $subcontractor_id
 * @property-read string $name
 * @property-read string $quantity_per_work_unit
 * @property-read string|null $unit_cost
 * @property-read string|null $notes
 * @property-read int $sort_order
 * @property-read WorkItemTemplate $template
 * @property-read InventoryItem|null $inventoryItem
 * @property-read UnitOfMeasure|null $unit
 * @property-read EquipmentCategory|null $equipmentCategory
 * @property-read WorkforceTrade|null $workforceTrade
 * @property-read Customer|null $subcontractor
 */
#[Fillable([
    'tenant_id',
    'work_item_template_id',
    'resource_type',
    'inventory_item_id',
    'unit_of_measure_id',
    'equipment_category_id',
    'workforce_trade_id',
    'subcontractor_id',
    'name',
    'quantity_per_work_unit',
    'unit_cost',
    'notes',
    'sort_order',
])]
final class WorkItemResourceTemplate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<WorkItemResourceTemplate>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'resource_type' => EstimateResourceType::class,
            'quantity_per_work_unit' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkItemTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkItemTemplate::class, 'work_item_template_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    /** @return BelongsTo<EquipmentCategory, $this> */
    public function equipmentCategory(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    /** @return BelongsTo<WorkforceTrade, $this> */
    public function workforceTrade(): BelongsTo
    {
        return $this->belongsTo(WorkforceTrade::class, 'workforce_trade_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'subcontractor_id');
    }

    /**
     * Resolves the effective unit cost, falling back to InventoryItem's default unit cost if available.
     */
    public function effectiveUnitCost(): float
    {
        if ($this->unit_cost !== null && is_numeric($this->unit_cost)) {
            return (float) $this->unit_cost;
        }

        if ($this->inventoryItem?->default_unit_cost !== null) {
            return (float) $this->inventoryItem->default_unit_cost;
        }

        return 0.0;
    }
}
