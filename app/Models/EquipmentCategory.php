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

#[Fillable([
    'tenant_id', 'code', 'name', 'description', 'default_meter_type',
    'default_capacity_unit', 'fuel_efficiency_basis', 'expected_fuel_efficiency',
    'fuel_tolerance_percent', 'is_active', 'created_by', 'updated_by',
])]
final class EquipmentCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentCategory>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'expected_fuel_efficiency' => 'decimal:4',
            'fuel_tolerance_percent' => 'decimal:4',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return HasMany<Equipment, $this> */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
