<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $equipment_id
 * @property-read string $branch_id
 * @property-read string $equipment_location_id
 * @property-read CarbonInterface $observed_at
 * @property-read string|null $observed_status
 * @property-read string|null $condition_observation
 * @property-read string|null $note
 * @property-read EquipmentLocation $location
 * @property-read User $confirmedBy
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'branch_id', 'equipment_location_id', 'project_id',
    'site_id', 'observed_at', 'latitude', 'longitude', 'observed_status',
    'condition_observation', 'note', 'confirmed_by', 'created_by', 'updated_by',
])]
final class EquipmentLocationConfirmation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'observed_at' => 'datetime', 'latitude' => 'decimal:7',
            'longitude' => 'decimal:7', 'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo { return $this->belongsTo(Equipment::class); }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function location(): BelongsTo { return $this->belongsTo(EquipmentLocation::class, 'equipment_location_id'); }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
}
