<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryStoreType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'branch_id', 'equipment_location_id', 'project_id', 'site_id', 'code', 'name', 'type', 'address', 'is_active', 'created_by', 'updated_by'])]
final class InventoryStore extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryStore>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['type' => InventoryStoreType::class, 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function equipmentLocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @param  Builder<InventoryStore>  $query
     * @return Builder<InventoryStore>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('branches.view-all')) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
