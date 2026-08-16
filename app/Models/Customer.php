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
 * @property-read string|null $branch_id
 * @property-read string $type
 * @property-read string $name
 * @property-read string $code
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $tax_number
 * @property-read string|null $address
 * @property-read string $status
 * @property-read string|null $created_by
 * @property-read string|null $updated_by
 * @property-read Branch|null $branch
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'type',
    'name',
    'code',
    'email',
    'phone',
    'tax_number',
    'address',
    'status',
    'created_by',
    'updated_by',
])]
final class Customer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<Customer>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const string TYPE_CLIENT = 'client';

    public const string TYPE_SUBCONTRACTOR = 'subcontractor';

    public const string TYPE_SUPPLIER = 'supplier';

    public const string TYPE_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'type' => 'string',
            'name' => 'string',
            'code' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'tax_number' => 'string',
            'address' => 'string',
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
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('branches.view-all')) {
            return $query;
        }

        $branchIds = $user->branches()->pluck('branches.id')->all();

        return $query->where(function (Builder $query) use ($branchIds): void {
            $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
        });
    }
}
