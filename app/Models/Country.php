<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $code
 * @property-read string $name
 * @property-read string $iso3_code
 * @property-read string $default_currency_code
 * @property-read bool $is_active
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Currency $defaultCurrency
 *
 * @method static CountryFactory factory($count = null, $state = [])
 */
#[WithoutIncrementing]
final class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'iso3_code',
        'default_currency_code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'code' => 'string',
            'name' => 'string',
            'iso3_code' => 'string',
            'default_currency_code' => 'string',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_code', 'code');
    }

    #[Scope]
    /**
     * @param  Builder<Country>  $query
     */
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtoupper($value),
        );
    }

    /**
     * @return Attribute<string, string>
     */
    protected function iso3Code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtoupper($value),
        );
    }

    /**
     * @return Attribute<string, string>
     */
    protected function defaultCurrencyCode(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtoupper($value),
        );
    }
}
