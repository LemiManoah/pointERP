<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UnitDimension;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'code', 'name', 'symbol', 'quantity_dimension', 'is_base_unit', 'is_active'])]
final class UnitOfMeasure extends Model
{
    /** @use HasFactory<Factory<UnitOfMeasure>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['tenant_id' => 'string', 'quantity_dimension' => UnitDimension::class, 'is_base_unit' => 'boolean', 'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
