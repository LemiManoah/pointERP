<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\DailySiteReportDelayLine;
use App\Models\DailySiteReportWorkLine;
use App\Models\Document;
use App\Models\EquipmentLocation;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimateLine;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Client-facing quarry scenario built from the connected regression fixture. */
final class QuarryDemoSeeder extends Seeder
{
    public function run(): void
    {
        new PointInvestmentSeeder(includeTestAliases: false)->run();

        $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
        $project->update([
            'reference' => 'NAK-QRY',
            'name' => 'Nakasongola Granite Quarry',
            'description' => 'Granite extraction, crushing, screening, stockpiling and aggregate dispatch operations.',
            'budget_amount' => '8500000000.0000',
        ]);

        Customer::query()->where('tenant_id', $project->tenant_id)->where('code', 'UNRA')->update([
            'code' => 'IRONPOINT-QRY',
            'name' => 'Ironpoint Quarry Operations',
            'address' => 'Nakasongola District, Uganda',
        ]);

        Contract::query()->whereKey($project->contract_id)->update([
            'reference' => 'IPO/QRY/2026/001',
            'title' => 'Nakasongola Granite Quarry Development and Aggregate Production',
            'scope_summary' => 'Quarry development, controlled extraction, crushing, screening and aggregate supply.',
            'contract_value' => '8500000000.0000',
            'payment_terms' => 'Monthly valuation based on verified tonnes of aggregate produced and dispatched.',
        ]);

        $sites = Site::query()->where('project_id', $project->id)->oldest()->get();
        $sites->get(0)?->update(['reference' => 'QUARRY-PIT', 'name' => 'Main Quarry Pit', 'location_name' => 'Extraction benches and controlled blasting area']);
        $sites->get(1)?->update(['reference' => 'CRUSHER-YARD', 'name' => 'Crushing and Stockpile Yard', 'location_name' => 'Primary crusher, screening plant, stockpiles and weighbridge']);

        $activityData = [
            ['QRY-001', 'Overburden stripping and pit preparation'],
            ['QRY-002', 'Blast-hole drilling'],
            ['QRY-003', 'Rock blasting'],
            ['QRY-004', 'Primary crushing'],
            ['QRY-005', 'Aggregate screening'],
            ['QRY-006', 'Stockpiling and dispatch'],
        ];

        foreach (ProjectActivity::query()->where('project_id', $project->id)->orderBy('sort_order')->get() as $index => $activity) {
            $replacement = $activityData[$index] ?? null;
            if ($replacement !== null) {
                $activity->update(['code' => $replacement[0], 'boq_item_number' => $replacement[0], 'name' => $replacement[1]]);
            }
        }

        foreach (ProjectEstimateLine::query()->whereHas('estimate', fn ($query) => $query->where('project_id', $project->id))->orderBy('sort_order')->get() as $index => $line) {
            $replacement = $activityData[$index] ?? null;
            if ($replacement !== null) {
                $line->update(['boq_reference' => $replacement[0], 'code' => $replacement[0], 'name' => $replacement[1]]);
            }
        }

        $documentTitles = [
            ['QRY-DRG-001', 'Approved quarry layout and extraction plan'],
            ['QRY-HSE-001', 'Quarry health and safety management plan'],
            ['QRY-BLAST-001', 'Controlled blasting method statement'],
            ['QRY-ENV-001', 'Environmental and dust-control plan'],
            ['QRY-LAB-001', 'Aggregate grading and quality test results'],
            ['QRY-DSR-001', 'Daily quarry production evidence'],
        ];

        foreach (Document::query()->where('tenant_id', $project->tenant_id)->oldest()->get() as $index => $document) {
            $replacement = $documentTitles[$index] ?? ['QRY-DOC-'.mb_str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'Quarry operational document'];
            $document->update(['reference' => $replacement[0], 'document_number' => $replacement[0], 'title' => $replacement[1]]);
        }

        $locationNames = ['Quarry Workshop and Fuel Bay', 'Main Quarry Pit', 'Crushing and Stockpile Yard'];
        foreach (EquipmentLocation::query()->where('tenant_id', $project->tenant_id)->oldest()->get() as $index => $location) {
            $location->update([
                'code' => 'QRY-LOC-'.mb_str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $locationNames[$index % count($locationNames)],
                'address' => 'Nakasongola Granite Quarry',
            ]);
        }

        DailySiteReportWorkLine::query()->where('tenant_id', $project->tenant_id)->update([
            'description' => 'Quarry production activity completed and measured during the shift.',
            'chainage_from' => null,
            'chainage_to' => null,
            'side' => null,
        ]);
        DailySiteReportDelayLine::query()->where('tenant_id', $project->tenant_id)->update([
            'description' => 'Production interruption recorded during quarry operations.',
        ]);

        DB::table('inventory_categories')->where('tenant_id', $project->tenant_id)->where('code', 'ROAD-MATERIALS')->update([
            'code' => 'QUARRY-MATERIALS',
            'name' => 'Quarry operating materials',
            'description' => 'Materials consumed in extraction, crushing, maintenance and quarry support operations.',
        ]);
        DB::table('project_estimates')->where('project_id', $project->id)->update(['title' => 'Approved quarry production estimate']);
        DB::table('expenses')->where('tenant_id', $project->tenant_id)->where('payee_name_snapshot', 'like', '%Busunju%')->update(['payee_name_snapshot' => 'Quarry site catering team']);
        DB::table('reporting_calendars')->where('project_id', $project->id)->update(['name' => 'Quarry production reporting calendar']);

        foreach (DB::table('material_requisitions')->where('project_id', $project->id)->oldest()->get(['id']) as $index => $requisition) {
            DB::table('material_requisitions')->where('id', $requisition->id)->update([
                'reference' => 'QRY-MR-'.mb_str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'department' => 'Quarry operations',
                'reason' => 'Materials required for scheduled quarry production and site support.',
            ]);
        }

        foreach (DB::table('daily_site_reports')->where('project_id', $project->id)->oldest('report_date')->get(['id']) as $index => $report) {
            DB::table('daily_site_reports')->where('id', $report->id)->update([
                'reference' => 'QRY-DSR-'.mb_str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'work_summary' => 'Extraction, crushing, screening and stockpile operations continued during the shift.',
                'visitor_summary' => 'Quarry supervision and production checks were completed.',
            ]);
        }
    }
}
