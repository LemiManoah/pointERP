<?php

declare(strict_types=1);

namespace App\Actions\Operations\Boq;

use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\ProjectActivity;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

final readonly class ApplyProgressCorrection
{
    /** @param list<array{line_id: string, quantity_delta?: string|null}> $adjustments */
    public function handle(DailySiteReportCorrection $correction, DailySiteReport $report, User $actor, array $adjustments): void
    {
        $seen = [];
        foreach ($adjustments as $adjustment) {
            $delta = BigDecimal::of($adjustment['quantity_delta'] ?? '0');
            if ($delta->isZero()) {
                continue;
            }

            $lineId = $adjustment['line_id'];
            $line = $report->workLines()->whereKey($lineId)->lockForUpdate()->first();
            $source = BoqProgressEntry::query()->where('project_id', $report->project_id)
                ->where('source_key', 'dsr:'.$lineId)->first();
            if (isset($seen[$lineId]) || ! $line || ! $source || ! $line->counts_towards_boq) {
                throw ValidationException::withMessages(['changes.work_adjustments' => 'Select each measured work line from this approved report once.']);
            }

            $seen[$lineId] = true;
            $sourceKey = 'correction:'.$correction->id.':'.$lineId;
            if (BoqProgressEntry::query()->where('source_key', $sourceKey)->exists()) {
                continue;
            }

            $total = BoqProgressEntry::query()->where('daily_site_report_work_line_id', $lineId)->sum('quantity');
            if (BigDecimal::of($total)->plus($delta)->isNegative()) {
                throw ValidationException::withMessages(['changes.work_adjustments' => 'A correction cannot reduce a report line below zero.']);
            }

            $activity = ProjectActivity::withTrashed()->where('project_id', $report->project_id)
                ->whereKey($source->project_activity_id)->lockForUpdate()->firstOrFail();
            BoqProgressEntry::query()->create([
                'tenant_id' => $report->tenant_id, 'project_id' => $report->project_id,
                'boq_item_id' => $source->boq_item_id, 'project_activity_id' => $activity->id,
                'estimate_line_id' => $source->getAttribute('estimate_line_id'),
                'daily_site_report_work_line_id' => $lineId, 'source_key' => $sourceKey,
                'quantity' => (string) $delta, 'unit' => $source->unit,
                'measurement_date' => $report->report_date, 'approved_by' => $actor->id,
                'description' => 'Correction: '.$correction->reason,
            ]);
            $activity->update(['approved_quantity' => BoqProgressEntry::query()->where('project_activity_id', $activity->id)->sum('quantity')]);
        }
    }
}
