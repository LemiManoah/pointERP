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

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string|null $customer_id
 * @property-read string|null $contract_id
 * @property-read string $reference
 * @property-read string $name
 * @property-read string|null $description
 * @property-read string|null $manager_id
 * @property-read string $base_currency_code
 * @property-read string|null $budget_amount
 * @property-read CarbonInterface|null $starts_on
 * @property-read CarbonInterface|null $ends_on
 * @property-read string|null $reporting_deadline
 * @property-read string $status
 * @property-read Branch $branch
 * @property-read Customer|null $customer
 * @property-read Contract|null $contract
 * @property-read User|null $manager
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'customer_id',
    'contract_id',
    'reference',
    'name',
    'description',
    'manager_id',
    'base_currency_code',
    'budget_amount',
    'starts_on',
    'ends_on',
    'reporting_deadline',
    'status',
    'created_by',
    'updated_by',
])]
final class Project extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<Project>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'customer_id' => 'string',
            'contract_id' => 'string',
            'reference' => 'string',
            'name' => 'string',
            'description' => 'string',
            'manager_id' => 'string',
            'base_currency_code' => 'string',
            'budget_amount' => 'decimal:4',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'reporting_deadline' => 'string',
            'status' => 'string',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<ProjectActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class);
    }

    /**
     * @return HasMany<ProjectEstimate, $this>
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(ProjectEstimate::class)->orderByDesc('version_number');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'can_manage'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('projects.view-all') || $user->can('branches.view-all')) {
            return $query;
        }

        $branchIds = $user->branches()->pluck('branches.id')->all();

        return $query
            ->whereIn('branch_id', $branchIds)
            ->where(function (Builder $query) use ($user): void {
                $query->where('manager_id', $user->id)
                    ->orWhereHas('users', fn (Builder $query) => $query->whereKey($user->id))
                    ->orWhereHas('sites.users', fn (Builder $query) => $query->whereKey($user->id));
            });
    }
}
