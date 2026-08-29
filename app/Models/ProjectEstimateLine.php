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
use Illuminate\Support\Collection;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $project_estimate_id
 * @property-read string|null $site_id
 * @property-read string $unit_of_measure_id
 * @property-read string $work_item_key
 * @property-read string|null $boq_reference
 * @property-read string|null $code
 * @property-read string $name
 * @property-read string $planned_quantity
 * @property-read string|null $selling_rate
 * @property-read string|null $estimated_unit_cost
 * @property-read int $sort_order
 * @property-read string|null $notes
 * @property-read ProjectEstimate $estimate
 * @property-read UnitOfMeasure $unit
 * @property-read Collection<int, EstimateResourceLine> $resources
 */
#[Fillable(['tenant_id', 'project_estimate_id', 'site_id', 'unit_of_measure_id', 'work_item_key', 'boq_reference', 'code', 'name', 'planned_quantity', 'selling_rate', 'estimated_unit_cost', 'sort_order', 'notes'])]
final class ProjectEstimateLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ProjectEstimateLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'selling_rate' => 'decimal:4',
            'estimated_unit_cost' => 'decimal:4',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
}
