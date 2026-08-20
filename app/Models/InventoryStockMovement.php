<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryMovementStatus;
use App\Enums\InventoryMovementType;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\InventoryStockMovementObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $quantity
 * @property-read string $original_quantity
 * @property-read string $conversion_multiplier
 * @property-read InventoryMovementType $movement_type
 * @property-read InventoryMovementStatus $status
 * @property-read CarbonInterface $posted_at
 * @property-read CarbonInterface|null $reversed_at
 */
#[ObservedBy([InventoryStockMovementObserver::class])]
#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'inventory_item_id', 'inventory_batch_id', 'movement_type', 'status', 'quantity', 'original_quantity', 'original_unit_id', 'conversion_multiplier', 'source_type', 'source_id', 'source_key', 'project_id', 'site_id', 'equipment_id', 'reversal_of_id', 'reason', 'posted_by', 'posted_at', 'reversed_by', 'reversed_at'])]
final class InventoryStockMovement extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryStockMovement>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['movement_type' => InventoryMovementType::class, 'status' => InventoryMovementStatus::class, 'quantity' => 'decimal:4', 'original_quantity' => 'decimal:4', 'conversion_multiplier' => 'decimal:10', 'posted_at' => 'datetime', 'reversed_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function originalUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'original_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<InventoryStockMovement, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
