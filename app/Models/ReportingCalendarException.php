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

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string|null $branch_id
 * @property-read string $reporting_calendar_id
 * @property-read CarbonInterface $exception_date
 * @property-read string $type
 * @property-read string $name
 * @property-read string|null $reason
 * @property-read string|null $created_by
 */
#[Fillable(['tenant_id', 'branch_id', 'reporting_calendar_id', 'exception_date', 'type', 'name', 'reason', 'created_by', 'updated_by'])]
final class ReportingCalendarException extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ReportingCalendarException>> */
    use HasFactory;

    use HasUuids;

    public const string TYPE_NON_WORKING = 'non_working';

    public const string TYPE_WORKING_OVERRIDE = 'working_override';

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'exception_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ReportingCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ReportingCalendar::class, 'reporting_calendar_id');
    }
}
