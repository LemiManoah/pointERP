<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $reference
 * @property-read string $currency_code
 * @property-read string $total_amount
 * @property-read string|null $notes
 * @property-read string $inspection_status
 * @property-read CarbonInterface $received_on
 * @property-read int $lines_count
 * @property-read Collection<int, InventoryGoodsReceiptLine> $lines
 */
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'supplier_id', 'purchase_order_id', 'source_key', 'reference', 'supplier_reference', 'received_on', 'currency_code', 'total_amount', 'inspection_status', 'notes', 'received_by', 'verified_by', 'verified_at'])]
final class InventoryGoodsReceipt extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryGoodsReceipt>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['received_on' => 'date', 'total_amount' => 'decimal:4', 'verified_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'supplier_id');
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<InventoryGoodsReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryGoodsReceiptLine::class);
    }
}
