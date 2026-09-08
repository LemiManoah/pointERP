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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string|null $code
 * @property-read string $category
 * @property-read string $name
 * @property-read string $unit_of_measure_id
 * @property-read string|null $default_selling_rate
 * @property-read string|null $default_unit_cost
 * @property-read string|null $specifications
 * @property-read bool $is_active
 * @property-read string|null $created_by
 * @property-read string|null $updated_by
 * @property-read UnitOfMeasure $unit
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read Collection<int, WorkItemResourceTemplate> $resources
 */
#[Fillable([
    'tenant_id',
    'code',
    'category',
    'name',
    'unit_of_measure_id',
    'default_selling_rate',
    'default_unit_cost',
    'specifications',
    'is_active',
    'created_by',
    'updated_by',
])]
final class WorkItemTemplate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<WorkItemTemplate>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'default_selling_rate' => 'decimal:4',
            'default_unit_cost' => 'decimal:4',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<WorkItemResourceTemplate, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(WorkItemResourceTemplate::class, 'work_item_template_id')->orderBy('sort_order');
    }

    /**
     * Calculate dynamic unit cost from its resource templates.
     */
    public function calculateDynamicUnitCost(): float
    {
        return (float) $this->resources->reduce(function (float $total, WorkItemResourceTemplate $resource): float {
            $cost = $resource->effectiveUnitCost();

            return $total + ((float) $resource->quantity_per_work_unit * $cost);
        }, 0.0);
    }
}
