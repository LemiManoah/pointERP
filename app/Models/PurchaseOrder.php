<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
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

/**
 * @property-read string $order_number
 * @property-read string $supplier_name_snapshot
 * @property-read string $supplier_code_snapshot
 * @property-read string $currency_code
 * @property-read PurchaseOrderStatus $status
 * @property-read CarbonInterface $order_date
 * @property-read CarbonInterface|null $expected_date
 * @property-read string $subtotal
 * @property-read string $discount_amount
 * @property-read string $tax_amount
 * @property-read string $total_amount
 * @property-read string|null $delivery_terms
 * @property-read string|null $payment_terms
 * @property-read string|null $notes
 * @property-read string|null $decision_reason
 * @property-read CarbonInterface|null $submitted_at
 * @property-read CarbonInterface|null $approved_at
 * @property-read CarbonInterface|null $reviewed_at
 * @property-read CarbonInterface|null $cancelled_at
 * @property-read CarbonInterface|null $closed_at
 */
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'supplier_id', 'order_number', 'supplier_name_snapshot', 'supplier_code_snapshot', 'order_date', 'expected_date', 'currency_code', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'delivery_terms', 'payment_terms', 'notes', 'decision_reason', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'reviewed_by', 'reviewed_at', 'cancelled_by', 'cancelled_at', 'closed_by', 'closed_at', 'created_by', 'updated_by'])]
final class PurchaseOrder extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PurchaseOrder>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'status' => PurchaseOrderStatus::class, 'subtotal' => 'decimal:4', 'discount_amount' => 'decimal:4', 'tax_amount' => 'decimal:4', 'total_amount' => 'decimal:4', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'reviewed_at' => 'datetime', 'cancelled_at' => 'datetime', 'closed_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
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
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** @return HasMany<InventoryGoodsReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(InventoryGoodsReceipt::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Returned], true);
    }

    /**
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->can('branches.view-all') ? $query : $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
