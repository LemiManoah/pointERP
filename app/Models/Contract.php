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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $customer_id
 * @property-read string $reference
 * @property-read string $title
 * @property-read string|null $scope_summary
 * @property-read string|null $contract_value
 * @property-read string $currency_code
 * @property-read CarbonInterface|null $starts_on
 * @property-read CarbonInterface|null $ends_on
 * @property-read string|null $retention_percent
 * @property-read string|null $payment_terms
 * @property-read string $status
 * @property-read Branch $branch
 * @property-read Customer $customer
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'customer_id',
    'reference',
    'title',
    'scope_summary',
    'contract_value',
    'currency_code',
    'starts_on',
    'ends_on',
    'retention_percent',
    'payment_terms',
    'status',
    'created_by',
    'updated_by',
])]
final class Contract extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<Contract>> */
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
            'reference' => 'string',
            'title' => 'string',
            'scope_summary' => 'string',
            'contract_value' => 'decimal:4',
            'currency_code' => 'string',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'retention_percent' => 'decimal:4',
            'payment_terms' => 'string',
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
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('branches.view-all')) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id')->all());
    }
}
