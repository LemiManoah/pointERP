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

/**
 * @property-read string $quantity
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $inventory_item_id
 * @property-read string $item_code_snapshot
 * @property-read string $item_name_snapshot
 * @property-read string|null $unit_symbol_snapshot
 * @property-read string $conversion_multiplier
 * @property-read string $stock_quantity
 * @property-read string|null $batch_number
 * @property-read CarbonInterface|null $manufactured_on
 * @property-read CarbonInterface|null $expires_on
 * @property-read InventoryItem $item
 * @property-read UnitOfMeasure $unit
 */
#[Fillable(['tenant_id', 'inventory_direct_receipt_id', 'inventory_item_id', 'unit_of_measure_id', 'inventory_batch_id', 'inventory_stock_movement_id', 'item_code_snapshot', 'item_name_snapshot', 'unit_symbol_snapshot', 'quantity', 'conversion_multiplier', 'stock_quantity', 'batch_number', 'manufactured_on', 'expires_on'])]
final class InventoryDirectReceiptLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryDirectReceiptLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'conversion_multiplier' => 'decimal:10', 'stock_quantity' => 'decimal:4', 'manufactured_on' => 'date', 'expires_on' => 'date', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryDirectReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InventoryDirectReceipt::class, 'inventory_direct_receipt_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    /** @return BelongsTo<InventoryBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    /** @return BelongsTo<InventoryStockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryStockMovement::class, 'inventory_stock_movement_id');
    }
}
