<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $code
 * @property-read string $name
 * @property-read string|null $symbol
 * @property-read int $decimal_places
 * @property-read bool $is_active
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 * @method static CurrencyFactory factory($count = null, $state = [])
 */
#[WithoutIncrementing]
#[Fillable([
    'code',
    'name',
    'symbol',
    'decimal_places',
    'is_active',
])]
final class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'code' => 'string',
            'name' => 'string',
            'symbol' => 'string',
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Currency>  $query
     */
    #[Scope]
    public function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return Attribute<string, string>
     */
    public function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtoupper($value),
        );
    }
}
