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

#[Fillable(['tenant_id', 'inventory_transfer_id', 'inventory_item_id', 'unit_of_measure_id', 'inventory_batch_id', 'quantity', 'conversion_multiplier', 'stock_quantity', 'item_code_snapshot', 'item_name_snapshot', 'unit_symbol_snapshot', 'sort_order'])]
final class InventoryTransferLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryTransferLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'conversion_multiplier' => 'decimal:10', 'stock_quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<InventoryTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
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
}
