<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property-read string|null $project_id
 * @property-read string|null $site_id
 * @property-read string|null $equipment_location_id
 * @property-read CarbonInterface $transacted_at
 * @property-read string $transaction_type
 * @property-read string $fuel_type
 * @property-read string $quantity
 * @property-read string $unit
 * @property-read string $source_type
 * @property-read string|null $provider_customer_id
 * @property-read string|null $source_name
 * @property-read string|null $unit_cost
 * @property-read string|null $total_cost
 * @property-read string|null $currency_code
 * @property-read string|null $meter_reading
 * @property-read string|null $tank_level_before
 * @property-read string|null $tank_level_after
 * @property-read bool $is_full_tank
 * @property-read string|null $voucher_reference
 * @property-read string|null $notes
 * @property-read string $exception_status
 * @property-read string|null $exception_reason
 * @property-read string $status
 * @property-read string|null $reversal_of_id
 * @property-read string|null $reversal_reason
 * @property-read string|null $daily_site_report_equipment_line_id
 * @property-read string $submitted_by
 * @property-read string|null $approved_by
 * @property-read CarbonInterface $submitted_at
 * @property-read CarbonInterface|null $approved_at
 * @property-read CarbonInterface|null $posted_at
 * @property-read CarbonInterface|null $reversed_at
 * @property-read Equipment $equipment
 * @property-read Customer|null $provider
 * @property-read Staff|null $receiver
 * @property-read User $submittedBy
 * @property-read User|null $approvedBy
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'branch_id', 'project_id', 'site_id',
    'equipment_location_id', 'transacted_at', 'transaction_type', 'fuel_type',
    'quantity', 'unit', 'source_type', 'provider_customer_id', 'source_name',
    'unit_cost', 'total_cost', 'currency_code', 'meter_reading', 'tank_level_before',
    'tank_level_after', 'is_full_tank', 'issued_by_user_id', 'received_by_staff_id',
    'voucher_reference', 'notes', 'daily_site_report_equipment_line_id',
    'exception_status', 'exception_reason', 'status', 'reversal_of_id',
    'reversal_reason', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at',
    'posted_by', 'posted_at', 'reversed_by', 'reversed_at', 'created_by', 'updated_by',
])]
final class EquipmentFuelTransaction extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentFuelTransaction>> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_POSTED = 'posted';

    public const string STATUS_REVERSED = 'reversed';

    public const array TRANSACTION_TYPES = ['issue', 'refuel', 'consumption', 'return', 'adjustment'];

    public const array SOURCE_TYPES = ['supplier', 'store', 'site_stock', 'mobile_bowser', 'other'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'transacted_at' => 'datetime', 'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4',
            'meter_reading' => 'decimal:4', 'tank_level_before' => 'decimal:4',
            'tank_level_after' => 'decimal:4', 'is_full_tank' => 'boolean',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime',
            'posted_at' => 'datetime', 'reversed_at' => 'datetime',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'provider_customer_id');
    }

    /** @return BelongsTo<Staff, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'received_by_staff_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<EquipmentFuelTransaction, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasMany<EquipmentFuelTransaction, $this> */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
