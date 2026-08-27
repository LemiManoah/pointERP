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

#[Fillable(['tenant_id', 'inventory_reconciliation_id', 'inventory_item_id', 'inventory_batch_id', 'system_quantity', 'counted_quantity', 'variance_quantity', 'item_code_snapshot', 'item_name_snapshot', 'unit_symbol_snapshot', 'sort_order'])]
final class InventoryReconciliationLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryReconciliationLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['system_quantity' => 'decimal:4', 'counted_quantity' => 'decimal:4', 'variance_quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<InventoryReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(InventoryReconciliation::class, 'inventory_reconciliation_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
