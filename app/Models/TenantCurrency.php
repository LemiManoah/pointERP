<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $currency_code
 * @property-read bool $is_enabled
 * @property-read bool $is_default
 * @property-read Currency $currency
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'tenant_id',
    'currency_code',
    'is_enabled',
    'is_default',
])]
final class TenantCurrency extends Model
{
    use BelongsToTenant;
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
            'currency_code' => 'string',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
