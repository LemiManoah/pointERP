<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'code', 'name', 'description', 'priority', 'is_active', 'created_by', 'updated_by'])]
final class InventoryPriceTier extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<InventoryPriceTier>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['priority' => 'integer', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** @return HasMany<InventoryItemPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(InventoryItemPrice::class);
    }
}
