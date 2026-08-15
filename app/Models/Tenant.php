<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $code
 * @property-read string $default_currency_code
 * @property-read bool $is_multibranch
 * @property-read bool $multi_currency_enabled
 * @property-read string $timezone
 * @property-read string $status
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read CarbonInterface|null $deleted_at
 * @property-read Currency $defaultCurrency
 *
 * @method static TenantFactory factory($count = null, $state = [])
 */
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
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
            'name' => 'string',
            'code' => 'string',
            'default_currency_code' => 'string',
            'is_multibranch' => 'boolean',
            'multi_currency_enabled' => 'boolean',
            'timezone' => 'string',
            'status' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_code', 'code');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<TenantCurrency, $this>
     */
    public function enabledCurrencies(): HasMany
    {
        return $this->hasMany(TenantCurrency::class);
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    #[Scope]
    public function active(Builder $query): void
    {
        $query->where('status', 'active');
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

    /**
     * @return Attribute<string, string>
     */
    public function defaultCurrencyCode(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtoupper($value),
        );
    }
}
