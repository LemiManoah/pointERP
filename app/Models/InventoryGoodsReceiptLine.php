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
 * @property-read string $id
 * @property-read string $quantity
 * @property-read string $accepted_quantity
 * @property-read string $rejected_quantity
 * @property-read string|null $rejection_reason
 * @property-read string|null $batch_number
 * @property-read CarbonInterface|null $expires_on
 * @property-read string|null $unit_cost
 * @property-read string|null $line_total
 * @property-read InventoryItem $item
 * @property-read UnitOfMeasure $unit
 */
#[Fillable(['tenant_id', 'inventory_goods_receipt_id', 'purchase_order_line_id', 'inventory_item_id', 'inventory_batch_id', 'inventory_stock_movement_id', 'quantity', 'accepted_quantity', 'rejected_quantity', 'rejection_reason', 'unit_of_measure_id', 'stock_quantity', 'unit_cost', 'line_total', 'batch_number', 'manufactured_on', 'expires_on'])]
final class InventoryGoodsReceiptLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryGoodsReceiptLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4', 'stock_quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'line_total' => 'decimal:4', 'manufactured_on' => 'date', 'expires_on' => 'date', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryGoodsReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InventoryGoodsReceipt::class, 'inventory_goods_receipt_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
