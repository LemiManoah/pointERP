<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $project_id
 * @property-read string|null $site_id
 * @property-read string $code
 * @property-read string|null $boq_item_number
 * @property-read string $name
 * @property-read string|null $unit
 * @property-read string|null $planned_quantity
 * @property-read string|null $approved_quantity
 * @property-read string|null $rate_amount
 * @property-read string|null $currency_code
 * @property-read string $status
 * @property-read int $sort_order
 * @property-read string $created_by
 * @property-read string|null $updated_by
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read CarbonInterface|null $deleted_at
 * @property-read Branch|null $branch
 * @property-read Project|null $project
 * @property-read Site|null $site
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'project_id',
    'site_id',
    'code',
    'boq_item_number',
    'name',
    'unit',
    'planned_quantity',
    'approved_quantity',
    'rate_amount',
    'currency_code',
    'status',
    'sort_order',
    'created_by',
    'updated_by',
])]
final class ProjectActivity extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ProjectActivity>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'project_id' => 'string',
            'site_id' => 'string',
            'code' => 'string',
            'boq_item_number' => 'string',
            'name' => 'string',
            'unit' => 'string',
            'planned_quantity' => 'decimal:4',
            'approved_quantity' => 'decimal:4',
            'rate_amount' => 'decimal:4',
            'currency_code' => 'string',
            'status' => 'string',
            'sort_order' => 'integer',
            'created_by' => 'string',
            'updated_by' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @param  Builder<ProjectActivity>  $query
     * @return Builder<ProjectActivity>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', function (Builder $query) use ($user): void {
            /** @var Builder<Project> $projectQuery */
            $projectQuery = $query;

            $projectQuery->visibleTo($user);
        });
    }
}
