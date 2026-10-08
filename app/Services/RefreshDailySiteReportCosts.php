<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReport;
use App\Models\BoqProgressEntry;

final class RefreshDailySiteReportCosts
{
    public function handle(DailySiteReport $report): void
    {
        $output = (float) $report->workLines()->sum('amount');
        if (in_array($report->status, [DailySiteReport::STATUS_APPROVED, DailySiteReport::STATUS_ARCHIVED], true)) {
            $lines = $report->workLines()->get()->keyBy('id');
            $adjustments = BoqProgressEntry::query()->whereIn('daily_site_report_work_line_id', $lines->keys())
                ->where('source_key', 'like', 'correction:%')->get();
            foreach ($adjustments as $adjustment) {
                $line = $lines->get($adjustment->getAttribute('daily_site_report_work_line_id'));
                $output += (float) $adjustment->quantity * (float) ($line?->rate_amount ?? 0);
            }
        }
        $input = (float) $report->labourLines()->sum('amount')
            + (float) $report->equipmentLines()->sum('amount')
            + (float) $report->materialLines()->sum('amount');

        $report->forceFill([
            'output_value' => $output,
            'input_cost' => $input,
            'profit_loss' => $output - $input,
        ])->save();
    }
}
