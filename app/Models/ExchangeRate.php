<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string|null $branch_id
 * @property-read string $from_currency_code
 * @property-read string $to_currency_code
 * @property-read string $rate
 * @property-read CarbonInterface $effective_date
 * @property-read CarbonInterface|null $expires_at
 * @property-read string $source
 * @property-read string $status
 * @property-read string|null $approved_by
 * @property-read CarbonInterface|null $approved_at
 * @property-read string $created_by
 * @property-read string|null $updated_by
 * @property-read Branch|null $branch
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'from_currency_code',
    'to_currency_code',
    'rate',
    'effective_date',
    'expires_at',
    'source',
    'status',
    'approved_by',
    'approved_at',
    'created_by',
    'updated_by',
])]
final class ExchangeRate extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_SUPERSEDED = 'superseded';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'rate' => 'decimal:10',
            'effective_date' => 'date',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
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
     * @param  Builder<ExchangeRate>  $query
     */
    #[Scope]
    protected function drafts(Builder $query): void
    {
        $query->where('status', self::STATUS_DRAFT);
    }
}
