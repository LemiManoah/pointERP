<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaterialRequisitionPriority;
use App\Enums\MaterialRequisitionStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $inventory_store_id
 * @property-read string $requesting_user_id
 * @property-read string $reference
 * @property-read MaterialRequisitionPriority $priority
 * @property-read MaterialRequisitionStatus $status
 * @property-read CarbonInterface $required_by_date
 */
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'requesting_user_id', 'project_id', 'site_id', 'reference', 'department', 'required_by_date', 'priority', 'status', 'reason', 'decision_reason', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'reviewed_by', 'reviewed_at', 'cancelled_by', 'cancelled_at', 'created_by', 'updated_by'])]
final class MaterialRequisition extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<MaterialRequisition>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['priority' => MaterialRequisitionPriority::class, 'status' => MaterialRequisitionStatus::class, 'required_by_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'reviewed_at' => 'datetime', 'cancelled_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requesting_user_id');
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

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<MaterialRequisitionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(MaterialRequisitionLine::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [MaterialRequisitionStatus::Draft, MaterialRequisitionStatus::Returned], true);
    }

    /**
     * @param  Builder<MaterialRequisition>  $query
     * @return Builder<MaterialRequisition>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('branches.view-all')) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
