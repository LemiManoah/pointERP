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

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $equipment_maintenance_work_order_id
 * @property-read string|null $part_code
 * @property-read string $part_name
 * @property-read string $quantity
 * @property-read string $unit
 * @property-read string|null $unit_cost
 * @property-read string|null $total_cost
 * @property-read string|null $currency_code
 */
#[Fillable([
    'tenant_id', 'equipment_maintenance_work_order_id', 'part_code', 'part_name',
    'quantity', 'unit', 'unit_cost', 'total_cost', 'currency_code',
    'provider_customer_id', 'provider_name', 'reference', 'notes',
])]
final class EquipmentMaintenancePartLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentMaintenancePartLine>> */
    use HasFactory;
    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4'];
    }

    /** @return BelongsTo<EquipmentMaintenanceWorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(EquipmentMaintenanceWorkOrder::class, 'equipment_maintenance_work_order_id');
    }
}
