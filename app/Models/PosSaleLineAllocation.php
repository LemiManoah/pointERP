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

/** @property-read string $id @property-read string|null $batch_number_snapshot */
#[Fillable(['tenant_id', 'pos_sale_line_id', 'inventory_batch_id', 'inventory_stock_movement_id', 'stock_quantity', 'batch_number_snapshot', 'expires_on_snapshot'])]
final class PosSaleLineAllocation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<PosSaleLineAllocation>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['stock_quantity' => 'decimal:4', 'expires_on_snapshot' => 'date', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<PosSaleLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(PosSaleLine::class, 'pos_sale_line_id');
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
