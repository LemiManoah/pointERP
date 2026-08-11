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
 * @property-read string $branch_id
 * @property-read string $daily_site_report_id
 * @property-read string $requested_by
 * @property-read string|null $approved_by
 * @property-read string $status
 * @property-read string $reason
 * @property-read array<string, mixed>|null $old_values
 * @property-read array<string, mixed>|null $new_values
 * @property-read CarbonInterface|null $approved_at
 * @property-read CarbonInterface|null $rejected_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_id', 'requested_by', 'approved_by', 'status', 'reason', 'old_values', 'new_values', 'approved_at', 'rejected_at'])]
final class DailySiteReportCorrection extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DailySiteReportCorrection>> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DailySiteReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class, 'daily_site_report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
