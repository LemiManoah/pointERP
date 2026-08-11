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
 * @property-read string $reviewed_by
 * @property-read string $action
 * @property-read string|null $remarks
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_id', 'reviewed_by', 'action', 'remarks'])]
final class DailySiteReportReview extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DailySiteReportReview>> */
    use HasFactory;

    use HasUuids;

    public const string ACTION_SUBMITTED = 'submitted';

    public const string ACTION_RETURNED = 'returned';

    public const string ACTION_APPROVED = 'approved';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
