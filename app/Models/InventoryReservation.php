<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryReservationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'inventory_store_id', 'inventory_item_id', 'source_type', 'source_id', 'reserved_quantity', 'issued_quantity', 'released_quantity', 'status', 'created_by', 'updated_by'])]
final class InventoryReservation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryReservation>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['status' => InventoryReservationStatus::class, 'reserved_quantity' => 'decimal:4', 'issued_quantity' => 'decimal:4', 'released_quantity' => 'decimal:4', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
