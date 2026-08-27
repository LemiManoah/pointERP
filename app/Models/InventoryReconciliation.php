<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryApprovalStatus;
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
 * @property-read string $reference
 * @property-read string $request_key
 * @property-read InventoryApprovalStatus $status
 * @property-read string $reason
 * @property-read string|null $decision_reason
 * @property-read CarbonInterface $requested_at
 * @property-read InventoryStore $store
 * @property-read User $requester
 * @property-read Collection<int, InventoryReconciliationLine> $lines
 */
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'reference', 'request_key', 'status', 'reason', 'decision_reason', 'requested_by', 'requested_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at'])]
final class InventoryReconciliation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryReconciliation>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['status' => InventoryApprovalStatus::class, 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<InventoryReconciliationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryReconciliationLine::class);
    }
}
