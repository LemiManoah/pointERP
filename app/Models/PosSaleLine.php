<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $item_code_snapshot
 * @property-read string $item_name_snapshot
 * @property-read string $unit_symbol_snapshot
 * @property-read string $quantity
 * @property-read string $unit_price
 * @property-read string $discount_amount
 * @property-read string $line_total
 */
#[Fillable(['tenant_id', 'pos_sale_id', 'inventory_item_id', 'unit_of_measure_id', 'inventory_item_price_id', 'quantity', 'conversion_multiplier', 'stock_quantity', 'unit_price', 'discount_amount', 'line_total', 'item_code_snapshot', 'item_name_snapshot', 'unit_symbol_snapshot', 'price_list_snapshot', 'price_override_reason', 'sort_order'])]
final class PosSaleLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PosSaleLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'conversion_multiplier' => 'decimal:10', 'stock_quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'discount_amount' => 'decimal:4', 'line_total' => 'decimal:4', 'sort_order' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<PosSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
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

    /** @return HasMany<PosSaleLineAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PosSaleLineAllocation::class);
    }
}
