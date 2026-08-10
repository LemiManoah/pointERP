<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

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
        return $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user));
    }
}
