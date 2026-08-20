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
 * @property-read float $multiplier
 * @property-read float $divisor
 * @property-read CarbonInterface|null $effective_from
 * @property-read CarbonInterface|null $effective_until
 * @property-read string|null $reason
 * @property-read bool $is_active
 */
#[Fillable(['tenant_id', 'inventory_item_id', 'from_unit_id', 'to_unit_id', 'multiplier', 'divisor', 'effective_from', 'effective_until', 'reason', 'is_active', 'created_by'])]
final class InventoryUnitConversion extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryUnitConversion>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['multiplier' => 'decimal:10', 'divisor' => 'decimal:10', 'effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_unit_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_unit_id');
    }
}
