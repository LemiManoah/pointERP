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
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'inventory_item_id', 'inventory_price_tier_id', 'branch_id', 'unit_of_measure_id', 'amount', 'minimum_quantity', 'effective_from', 'effective_until', 'is_active', 'created_by', 'updated_by'])]
final class InventoryItemPrice extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryItemPrice>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['amount' => 'decimal:4', 'minimum_quantity' => 'decimal:4', 'effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryPriceTier, $this> */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(InventoryPriceTier::class, 'inventory_price_tier_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
