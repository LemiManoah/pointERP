<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $currency_code
 * @property-read bool $is_enabled
 * @property-read bool $is_default_transaction_currency
 * @property-read bool $can_receive
 * @property-read bool $can_pay
 * @property-read Branch $branch
 * @property-read Currency $currency
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'currency_code',
    'is_enabled',
    'is_default_transaction_currency',
    'can_receive',
    'can_pay',
])]
final class BranchCurrency extends Model
{
    use BelongsToTenant;
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
            'currency_code' => 'string',
            'is_enabled' => 'boolean',
            'is_default_transaction_currency' => 'boolean',
            'can_receive' => 'boolean',
            'can_pay' => 'boolean',
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
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
