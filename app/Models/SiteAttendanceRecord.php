<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\DsrLabourSource;
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
 * @property-read string $branch_id
 * @property-read string $site_attendance_register_id
 * @property-read string|null $staff_id
 * @property-read string|null $subcontractor_id
 * @property-read string $workforce_trade_id
 * @property-read DsrLabourSource $labour_source
 * @property-read string|null $worker_name_snapshot
 * @property-read string|null $subcontractor_name_snapshot
 * @property-read int $headcount
 * @property-read AttendanceStatus $attendance_status
 * @property-read string $regular_hours_per_person
 * @property-read string $overtime_hours_per_person
 * @property-read WorkforceTrade $trade
 */ #[Fillable([
    'tenant_id',
    'branch_id',
    'site_attendance_register_id',
    'staff_id',
    'subcontractor_id',
    'workforce_trade_id',
    'labour_source',
    'worker_name_snapshot',
    'subcontractor_name_snapshot',
    'headcount',
    'attendance_status',
    'regular_hours_per_person',
    'overtime_hours_per_person',
    'notes',
])]
final class SiteAttendanceRecord extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<SiteAttendanceRecord>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'labour_source' => DsrLabourSource::class,
            'attendance_status' => AttendanceStatus::class,
            'headcount' => 'integer',
            'regular_hours_per_person' => 'decimal:2',
            'overtime_hours_per_person' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SiteAttendanceRegister, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(SiteAttendanceRegister::class, 'site_attendance_register_id');
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'subcontractor_id');
    }

    /** @return BelongsTo<WorkforceTrade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(WorkforceTrade::class, 'workforce_trade_id');
    }

    public function personHours(): float
    {
        return $this->headcount * ((float) $this->regular_hours_per_person + (float) $this->overtime_hours_per_person);
    }
}
