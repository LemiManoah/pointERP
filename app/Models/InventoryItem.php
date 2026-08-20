<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryMaterialClass;
use App\Enums\InventoryTrackingType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $code
 * @property-read string $name
 * @property-read string|null $description
 * @property-read InventoryMaterialClass $material_class
 * @property-read InventoryTrackingType $tracking_type
 * @property-read string|null $batch_number
 * @property-read bool $is_expires
 * @property-read bool $is_for_sale
 * @property-read float|null $minimum_stock
 * @property-read float|null $reorder_quantity
 * @property-read float|null $default_unit_cost
 * @property-read float|null $default_selling_price
 * @property-read bool $is_active
 */
#[Fillable([
    'tenant_id', 'inventory_category_id', 'stock_unit_id', 'preferred_supplier_id',
    'code', 'name', 'description', 'material_class',
    'minimum_stock', 'reorder_quantity', 'default_unit_cost', 'default_selling_price',
    'tracking_type', 'batch_number',
    'is_expires', 'is_for_sale', 'is_active', 'created_by', 'updated_by',
])]
final class InventoryItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryItem>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'default_unit_cost' => 'decimal:4',
            'default_selling_price' => 'decimal:4',
            'material_class' => InventoryMaterialClass::class,
            'tracking_type' => InventoryTrackingType::class,
            'is_expires' => 'boolean',
            'is_for_sale' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InventoryCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'stock_unit_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'preferred_supplier_id');
    }

    /** @return HasMany<InventoryUnitConversion, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(InventoryUnitConversion::class);
    }

    /** @return HasMany<InventoryItemPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(InventoryItemPrice::class);
    }

    /** @return HasMany<InventoryBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    /** @return HasMany<InventoryStoreItem, $this> */
    public function storeSettings(): HasMany
    {
        return $this->hasMany(InventoryStoreItem::class);
    }

    /** @return HasMany<InventoryStockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class);
    }
}
