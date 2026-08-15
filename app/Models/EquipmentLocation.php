<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id', 'branch_id', 'project_id', 'site_id', 'type', 'code', 'name',
    'address', 'latitude', 'longitude', 'is_active', 'created_by', 'updated_by',
])]
final class EquipmentLocation extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public const array TYPES = ['depot', 'yard', 'workshop', 'site', 'other'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }

    /** @param Builder<EquipmentLocation> $query @return Builder<EquipmentLocation> */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('equipment.view-all') || $user->can('branches.view-all')) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
