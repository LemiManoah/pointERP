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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'branch_id',
    'project_id',
    'reference',
    'name',
    'location_name',
    'latitude',
    'longitude',
    'manager_id',
    'reporting_deadline',
    'status',
    'created_by',
    'updated_by',
])]
final class Site extends Model
{
    /** @use HasFactory<Factory<Site>> */
    use HasFactory;

    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'project_id' => 'string',
            'reference' => 'string',
            'name' => 'string',
            'location_name' => 'string',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'manager_id' => 'string',
            'reporting_deadline' => 'string',
            'status' => 'string',
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

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<ProjectActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'can_submit_dsr', 'can_review_dsr'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('sites.view-all') || $user->can('projects.view-all') || $user->can('branches.view-all')) {
            return $query;
        }

        $branchIds = $user->branches()->pluck('branches.id')->all();

        return $query
            ->whereIn('branch_id', $branchIds)
            ->where(function (Builder $query) use ($user): void {
                $query->where('manager_id', $user->id)
                    ->orWhereHas('users', fn (Builder $query) => $query->whereKey($user->id))
                    ->orWhereHas('project.users', fn (Builder $query) => $query->whereKey($user->id));
            });
    }
}
