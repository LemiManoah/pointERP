<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $equipment_id
 * @property-read string $branch_id
 * @property-read string $maintenance_type
 * @property-read string $name
 * @property-read string $basis
 * @property-read int|null $interval_days
 * @property-read string|null $interval_meter_units
 * @property-read CarbonInterface|null $last_service_date
 * @property-read string|null $last_service_reading
 * @property-read CarbonInterface|null $next_due_date
 * @property-read string|null $next_due_reading
 * @property-read int $warning_days
 * @property-read string $warning_meter_units
 * @property-read string|null $responsible_user_id
 * @property-read bool $is_active
 * @property-read string|null $last_notified_status
 * @property-read CarbonInterface|null $last_notified_at
 * @property-read Equipment $equipment
 * @property-read Branch $branch
 * @property-read User|null $responsibleUser
 * @property-read Collection<int, EquipmentMaintenanceWorkOrder> $workOrders
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'branch_id', 'maintenance_type', 'name', 'basis',
    'interval_days', 'interval_meter_units', 'last_service_date', 'last_service_reading',
    'next_due_date', 'next_due_reading', 'warning_days', 'warning_meter_units',
    'responsible_user_id', 'is_active', 'last_notified_status', 'last_notified_at',
    'created_by', 'updated_by',
])]
final class EquipmentMaintenanceSchedule extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentMaintenanceSchedule>> */
    use HasFactory;

    use HasUuids;

    public const array BASES = ['date', 'meter', 'whichever_first'];

    public const array TYPES = ['preventive_service', 'inspection', 'certification', 'lubrication', 'tyres', 'other'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'interval_days' => 'integer', 'interval_meter_units' => 'decimal:4',
            'last_service_date' => 'date', 'last_service_reading' => 'decimal:4',
            'next_due_date' => 'date', 'next_due_reading' => 'decimal:4',
            'warning_days' => 'integer', 'warning_meter_units' => 'decimal:4',
            'is_active' => 'boolean', 'last_notified_at' => 'datetime',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return HasMany<EquipmentMaintenanceWorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(EquipmentMaintenanceWorkOrder::class);
    }

    public function dueStatus(?CarbonInterface $asOf = null): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $pointInTime = $asOf ?? now();
        $currentReading = $this->equipment->current_meter_reading;
        $dateOverdue = $this->next_due_date !== null && $this->next_due_date->lt($pointInTime);
        $meterOverdue = $this->next_due_reading !== null && $currentReading !== null
            && (float) $currentReading >= (float) $this->next_due_reading;

        if ($dateOverdue || $meterOverdue) {
            return 'overdue';
        }

        $dateDueSoon = $this->next_due_date !== null && $this->next_due_date->lte($pointInTime->copy()->addDays($this->warning_days));
        $meterDueSoon = $this->next_due_reading !== null && $currentReading !== null
            && (float) $currentReading >= (float) $this->next_due_reading - (float) $this->warning_meter_units;

        return $dateDueSoon || $meterDueSoon ? 'due_soon' : 'current';
    }
}
