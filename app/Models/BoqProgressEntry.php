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

/**
 * @property-read string $id
 * @property-read string $boq_item_id
 * @property-read string $project_activity_id
 * @property-read numeric-string $quantity
 * @property-read string $unit
 * @property-read CarbonInterface $measurement_date
 * @property-read string $source_key
 * @property-read string $description
 */
#[Fillable(['tenant_id', 'project_id', 'boq_item_id', 'project_activity_id', 'estimate_line_id', 'daily_site_report_work_line_id', 'source_key', 'quantity', 'unit', 'measurement_date', 'approved_by', 'description'])]
final class BoqProgressEntry extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<BoqProgressEntry>> */
    use HasFactory;

    use HasUuids;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'measurement_date' => 'date'];
    }
}
