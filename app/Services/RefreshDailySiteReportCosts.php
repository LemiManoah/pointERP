<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReport;

final class RefreshDailySiteReportCosts
{
    public function handle(DailySiteReport $report): void
    {
        $output = (float) $report->workLines()->sum('amount');
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
