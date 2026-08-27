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

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $source_store_id
 * @property-read string $destination_store_id
 * @property-read string $reference
 * @property-read InventoryApprovalStatus $status
 * @property-read string $reason
 * @property-read CarbonInterface $requested_at
 */
#[Fillable(['tenant_id', 'branch_id', 'source_store_id', 'destination_store_id', 'reference', 'request_key', 'status', 'reason', 'decision_reason', 'requested_by', 'requested_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at'])]
final class InventoryTransfer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryTransfer>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['status' => InventoryApprovalStatus::class, 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function sourceStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'source_store_id');
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'destination_store_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<InventoryTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryTransferLine::class);
    }
}
