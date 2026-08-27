<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string|null $inventory_item_id
 * @property-read string|null $unit_of_measure_id
 * @property-read string $item_code_snapshot
 * @property-read string $item_name_snapshot
 * @property-read string $unit_code_snapshot
 * @property-read string $unit_symbol_snapshot
 * @property-read string $ordered_quantity
 * @property-read string $conversion_multiplier
 * @property-read string $stock_quantity
 * @property-read string $unit_price
 * @property-read string $line_amount
 * @property-read string $accepted_quantity
 * @property-read string $rejected_quantity
 * @property-read string $cancelled_quantity
 * @property-read string $price_source
 */
#[Fillable(['tenant_id', 'purchase_order_id', 'inventory_item_id', 'unit_of_measure_id', 'item_code_snapshot', 'item_name_snapshot', 'unit_code_snapshot', 'unit_symbol_snapshot', 'ordered_quantity', 'conversion_multiplier', 'stock_quantity', 'unit_price', 'price_source', 'line_amount', 'accepted_quantity', 'rejected_quantity', 'cancelled_quantity', 'sort_order'])]
final class PurchaseOrderLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PurchaseOrderLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['ordered_quantity' => 'decimal:4', 'conversion_multiplier' => 'decimal:10', 'stock_quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'line_amount' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4', 'cancelled_quantity' => 'decimal:4', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    /** @return HasMany<InventoryGoodsReceiptLine, $this> */
    public function receiptLines(): HasMany
    {
        return $this->hasMany(InventoryGoodsReceiptLine::class);
    }

    public function outstandingQuantity(): string
    {
        return (string) BigDecimal::of((string) $this->ordered_quantity)->minus((string) $this->accepted_quantity)->minus((string) $this->cancelled_quantity)->toScale(4, RoundingMode::HalfUp);
    }
}
