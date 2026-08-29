<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosSalePaymentStatus;
use App\Enums\PosSaleStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $inventory_store_id
 * @property-read string $inventory_price_tier_id
 * @property-read string|null $customer_id
 * @property-read string $sold_by
 * @property-read string $sale_number
 * @property-read string $checkout_key
 * @property-read PosSaleStatus $status
 * @property-read string $currency_code
 * @property-read string $total_amount
 * @property-read string $subtotal
 * @property-read string $discount_total
 * @property-read string $amount_paid
 * @property-read string $balance_due
 * @property-read PosSalePaymentStatus $payment_status
 * @property-read string|null $notes
 * @property-read int $lines_count
 * @property-read CarbonInterface|null $completed_at
 */
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'customer_id', 'inventory_price_tier_id', 'sale_number', 'checkout_key', 'status', 'currency_code', 'subtotal', 'discount_total', 'total_amount', 'amount_paid', 'balance_due', 'payment_status', 'notes', 'sold_by', 'completed_by', 'completed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
final class PosSale extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PosSale>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['status' => PosSaleStatus::class, 'payment_status' => PosSalePaymentStatus::class, 'subtotal' => 'decimal:4', 'discount_total' => 'decimal:4', 'total_amount' => 'decimal:4', 'amount_paid' => 'decimal:4', 'balance_due' => 'decimal:4', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<InventoryPriceTier, $this> */
    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(InventoryPriceTier::class, 'inventory_price_tier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /** @return HasMany<PosSaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PosSaleLine::class);
    }

    /** @return HasMany<PosPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    /** @return HasMany<PosReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(PosReturn::class);
    }
}
