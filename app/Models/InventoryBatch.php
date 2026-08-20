<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryBatchStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $batch_number
 * @property-read CarbonInterface|null $manufactured_on
 * @property-read CarbonInterface|null $expires_on
 * @property-read InventoryBatchStatus $status
 * @property-read string|null $notes
 * @property-read bool $is_active
 */
#[Fillable(['tenant_id', 'inventory_item_id', 'inventory_store_id', 'batch_number', 'manufactured_on', 'expires_on', 'status', 'notes', 'is_active', 'created_by', 'updated_by'])]
final class InventoryBatch extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryBatch>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['manufactured_on' => 'date', 'expires_on' => 'date', 'status' => InventoryBatchStatus::class, 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryStore, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }
}
