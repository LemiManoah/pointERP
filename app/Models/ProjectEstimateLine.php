<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BoqItemType;
use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property-read string|null $boq_item_id
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $project_estimate_id
 * @property-read string|null $site_id
 * @property-read string $unit_of_measure_id
 * @property-read BoqItemType $item_type
 * @property-read string|null $bill
 * @property-read string|null $section
 * @property-read string|null $element
 * @property-read string|null $description
 * @property-read string|null $source_document
 * @property-read string|null $source_sheet
 * @property-read int|null $source_row
 * @property-read string $work_item_key
 * @property-read string|null $boq_reference
 * @property-read string|null $code
 * @property-read string $name
 * @property-read string $planned_quantity
 * @property-read string|null $selling_rate
 * @property-read string|null $percentage_rate
 * @property-read list<string>|null $percentage_base_keys
 * @property-read string|null $daywork_resource_type
 * @property-read string|null $daywork_inventory_item_id
 * @property-read string|null $daywork_equipment_category_id
 * @property-read string|null $daywork_workforce_trade_id
 * @property-read string|null $estimated_unit_cost
 * @property-read int $sort_order
 * @property-read string|null $notes
 * @property-read ProjectEstimate $estimate
 * @property-read UnitOfMeasure $unit
 * @property-read Collection<int, EstimateResourceLine> $resources
 * @property-read InventoryItem|null $dayworkInventoryItem
 * @property-read EquipmentCategory|null $dayworkEquipmentCategory
 * @property-read WorkforceTrade|null $dayworkWorkforceTrade
 */
#[Fillable([
    'percentage_rate', 'percentage_base_keys', 'daywork_resource_type', 'daywork_inventory_item_id',
    'daywork_equipment_category_id', 'daywork_workforce_trade_id',
    'boq_item_id', 'tenant_id', 'project_estimate_id', 'site_id', 'unit_of_measure_id', 'work_item_key', 'boq_reference', 'code', 'name', 'planned_quantity', 'selling_rate', 'estimated_unit_cost', 'sort_order', 'notes', 'bill', 'section', 'element', 'item_type', 'description', 'source_document', 'source_sheet', 'source_row'])]
final class ProjectEstimateLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ProjectEstimateLine>> */
    use HasFactory;

    use HasUuids;

    protected $attributes = ['item_type' => 'measured'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'item_type' => BoqItemType::class,
            'source_row' => 'integer',
            'planned_quantity' => 'decimal:4',
            'selling_rate' => 'decimal:4',
            'percentage_rate' => 'decimal:4',
            'percentage_base_keys' => 'array',
            'estimated_unit_cost' => 'decimal:4',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @param Collection<int, ProjectEstimateLine> $lines */
    public function boqAmount(Collection $lines): ?string
    {
        $amounts = [];

        return $this->calculateBoqAmount($lines, $amounts, []);
    }

    /** @return BelongsTo<ProjectEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(ProjectEstimate::class, 'project_estimate_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    /** @return HasMany<EstimateResourceLine, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(EstimateResourceLine::class, 'project_estimate_line_id')->orderBy('sort_order');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function dayworkInventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'daywork_inventory_item_id');
    }

    /** @return BelongsTo<EquipmentCategory, $this> */
    public function dayworkEquipmentCategory(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'daywork_equipment_category_id');
    }

    /** @return BelongsTo<WorkforceTrade, $this> */
    public function dayworkWorkforceTrade(): BelongsTo
    {
        return $this->belongsTo(WorkforceTrade::class, 'daywork_workforce_trade_id');
    }

    /**
     * @param  Collection<int, ProjectEstimateLine>  $lines
     * @param  array<string, string|null>  $amounts
     * @param  array<string, bool>  $visited
     */
    private function calculateBoqAmount(Collection $lines, array &$amounts, array $visited): ?string
    {
        if (array_key_exists($this->work_item_key, $amounts)) {
            return $amounts[$this->work_item_key];
        }

        if (isset($visited[$this->work_item_key]) || count($visited) >= 100) {
            return $amounts[$this->work_item_key] = null;
        }

        $visited[$this->work_item_key] = true;
        if ($this->item_type !== BoqItemType::PercentageAdjustment) {
            return $amounts[$this->work_item_key] = $this->selling_rate === null ? null : (string) BigDecimal::of($this->planned_quantity)
                ->multipliedBy($this->selling_rate)->toScale(4, RoundingMode::HalfUp);
        }

        $keys = $this->percentage_base_keys ?? [];
        if ($this->percentage_rate === null || $keys === []) {
            return $amounts[$this->work_item_key] = null;
        }

        $bases = $lines->whereIn('work_item_key', $keys);
        if ($bases->count() !== count($keys)) {
            return $amounts[$this->work_item_key] = null;
        }

        $total = BigDecimal::zero();
        foreach ($bases as $base) {
            $amount = $base->calculateBoqAmount($lines, $amounts, $visited);
            if ($amount === null) {
                return $amounts[$this->work_item_key] = null;
            }

            $total = $total->plus($amount);
        }

        return $amounts[$this->work_item_key] = (string) $total->multipliedBy($this->percentage_rate)->dividedBy(100, 4, RoundingMode::HalfUp);
    }
}
