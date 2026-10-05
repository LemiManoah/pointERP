<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('project_estimate_lines')->orderBy('id')->chunkById(250, function ($lines): void {
                foreach ($lines as $line) {
                    $estimate = DB::table('project_estimates')->where('id', $line->project_estimate_id)->first();
                    $item = DB::table('project_boq_items')->where('project_id', $estimate->project_id)->where('work_item_key', $line->work_item_key)->first();
                    $id = $item->id ?? (string) Str::uuid();
                    if (! $item) {
                        DB::table('project_boq_items')->insert(['id' => $id, 'tenant_id' => $estimate->tenant_id,
                            'project_id' => $estimate->project_id, 'work_item_key' => $line->work_item_key,
                            'created_at' => now(), 'updated_at' => now()]);
                    }

                    DB::table('project_estimate_lines')->where('id', $line->id)->update(['boq_item_id' => $id]);
                    DB::table('project_activities')->where('project_id', $estimate->project_id)
                        ->where('estimate_work_item_key', $line->work_item_key)->update(['boq_item_id' => $id]);
                }
            });
            DB::table('project_activities')->whereNotNull('boq_item_id')->orderBy('id')->chunkById(100, function ($activities): void {
                foreach ($activities as $activity) {
                    $sum = BigDecimal::zero();
                    $lines = DB::table('daily_site_report_work_lines as lines')
                        ->join('daily_site_reports as reports', 'reports.id', '=', 'lines.daily_site_report_id')
                        ->where('lines.project_activity_id', $activity->id)
                        ->where('reports.project_id', $activity->project_id)
                        ->where('reports.tenant_id', $activity->tenant_id)
                        ->select('lines.*', 'reports.status as report_status', 'reports.report_date', 'reports.approved_by')->get();
                    foreach ($lines as $line) {
                        if ($line->unit !== $activity->unit) {
                            continue;
                        }

                        DB::table('daily_site_report_work_lines')->where('id', $line->id)
                            ->update(['boq_item_id' => $activity->boq_item_id, 'counts_towards_boq' => true]);
                        if ($line->report_status !== 'approved') {
                            continue;
                        }

                        $sum = $sum->plus($line->quantity);
                        DB::table('boq_progress_entries')->insert([
                            'id' => (string) Str::uuid(), 'tenant_id' => $activity->tenant_id,
                            'project_id' => $activity->project_id, 'boq_item_id' => $activity->boq_item_id,
                            'project_activity_id' => $activity->id, 'estimate_line_id' => $activity->estimate_line_id,
                            'daily_site_report_work_line_id' => $line->id, 'source_key' => 'dsr:'.$line->id,
                            'quantity' => $line->quantity, 'unit' => $line->unit,
                            'measurement_date' => $line->report_date, 'approved_by' => $line->approved_by,
                            'description' => $line->description, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }

                    $difference = BigDecimal::of($activity->approved_quantity ?? '0')->minus($sum);
                    if (! $difference->isZero()) {
                        DB::table('boq_progress_entries')->insert([
                            'id' => (string) Str::uuid(), 'tenant_id' => $activity->tenant_id,
                            'project_id' => $activity->project_id, 'boq_item_id' => $activity->boq_item_id,
                            'project_activity_id' => $activity->id, 'estimate_line_id' => $activity->estimate_line_id,
                            'source_key' => 'legacy:'.$activity->id, 'quantity' => (string) $difference,
                            'unit' => $activity->unit ?? '', 'measurement_date' => now()->toDateString(),
                            'description' => 'Legacy balance reconciliation: preserves the pre-migration approved quantity. Requires review against original site records.',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            });
        });
    }

    public function down(): void
    {
        // Data is retained; rolling back the preceding schema migrations removes the new tables and links.
    }
};
