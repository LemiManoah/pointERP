<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryDirectReceiptReason;
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
 * @property-read string $inventory_store_id
 * @property-read string|null $source_company_id
 * @property-read string $receipt_key
 * @property-read string $reference
 * @property-read string|null $source_reference
 * @property-read InventoryDirectReceiptReason $reason
 * @property-read CarbonInterface $received_on
 * @property-read InventoryStore $store
 * @property-read Customer|null $sourceCompany
 * @property-read User $receiver
 * @property-read Collection<int, InventoryDirectReceiptLine> $lines
 */
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'source_company_id', 'receipt_key', 'reference', 'source_reference', 'received_on', 'reason', 'received_by'])]
final class InventoryDirectReceipt extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryDirectReceipt>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['reason' => InventoryDirectReceiptReason::class, 'received_on' => 'date', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
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
    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'source_company_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<InventoryDirectReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryDirectReceiptLine::class);
    }
}
