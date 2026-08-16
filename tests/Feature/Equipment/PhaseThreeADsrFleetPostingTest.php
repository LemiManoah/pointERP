<?php

declare(strict_types=1);

use App\Actions\Operations\DailySiteReports\PostApprovedDsrEquipmentLines;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentUsageLog;
use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('posts approved DSR equipment usage and fuel exactly once', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'KIBOGA-HOIMA')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $opening = (float) $roller->current_meter_reading;
    $closing = $opening + 10;

    $report = DailySiteReport::query()->create([
        'tenant_id' => $site->tenant_id, 'branch_id' => $site->branch_id,
        'project_id' => $site->project_id, 'site_id' => $site->id,
        'report_date' => now()->subDay()->toDateString(), 'reference' => 'DSR-FLEET-POSTING-001',
        'work_summary' => 'Compaction operations with controlled plant evidence.',
        'status' => DailySiteReport::STATUS_SUBMITTED, 'submitted_by' => $engineer->id,
        'submitted_at' => now()->subHours(2), 'created_by' => $engineer->id, 'updated_by' => $engineer->id,
    ]);
    $line = DailySiteReportEquipmentLine::query()->create([
        'tenant_id' => $report->tenant_id, 'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id, 'equipment_id' => $roller->id,
        'equipment_name' => $roller->name, 'equipment_identifier' => $roller->asset_code,
        'status' => 'working', 'working_hours' => '10.0000', 'idle_hours' => '1.0000',
        'opening_meter_reading' => number_format($opening, 4, '.', ''),
        'closing_meter_reading' => number_format($closing, 4, '.', ''),
        'fuel_type' => 'diesel', 'fuel_quantity' => '300.0000',
        'fuel_transaction_type' => 'consumption', 'evidence_note' => 'Signed operator and fuel issue logs checked.',
        'fleet_posting_status' => 'unposted',
    ]);

    $this->actingAs($manager)->post(route('daily-site-reports.approve', $report))->assertRedirect(route('daily-site-reports.show', $report));

    expect($line->refresh()->fleet_posting_status)->toBe('posted')
        ->and(EquipmentUsageLog::query()->where('daily_site_report_equipment_line_id', $line->id)->count())->toBe(1)
        ->and(EquipmentFuelTransaction::query()->where('daily_site_report_equipment_line_id', $line->id)->count())->toBe(1);

    $fuel = EquipmentFuelTransaction::query()->where('daily_site_report_equipment_line_id', $line->id)->firstOrFail();
    expect($fuel->status)->toBe(EquipmentFuelTransaction::STATUS_POSTED)
        ->and($fuel->exception_status)->toBe('review_required');

    resolve(PostApprovedDsrEquipmentLines::class)->handle($report->refresh(), $manager);
    expect(EquipmentUsageLog::query()->where('daily_site_report_equipment_line_id', $line->id)->count())->toBe(1)
        ->and(EquipmentFuelTransaction::query()->where('daily_site_report_equipment_line_id', $line->id)->count())->toBe(1);
});

it('labels fuel without comparable evidence instead of presenting a false normal rate', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'KIBOGA-HOIMA')->firstOrFail();
    $roller = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $report = DailySiteReport::query()->create([
        'tenant_id' => $site->tenant_id, 'branch_id' => $site->branch_id,
        'project_id' => $site->project_id, 'site_id' => $site->id,
        'report_date' => now()->subDay()->toDateString(), 'reference' => 'DSR-FLEET-EVIDENCE-002',
        'status' => DailySiteReport::STATUS_APPROVED, 'submitted_by' => $manager->id,
        'submitted_at' => now()->subHours(2), 'approved_by' => $manager->id,
        'approved_at' => now(), 'created_by' => $manager->id, 'updated_by' => $manager->id,
    ]);
    $line = DailySiteReportEquipmentLine::query()->create([
        'tenant_id' => $report->tenant_id, 'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id, 'equipment_id' => $roller->id,
        'equipment_name' => $roller->name, 'equipment_identifier' => $roller->asset_code,
        'status' => 'working', 'fuel_type' => 'diesel', 'fuel_quantity' => '80.0000',
        'fuel_transaction_type' => 'consumption', 'fleet_posting_status' => 'unposted',
    ]);

    resolve(PostApprovedDsrEquipmentLines::class)->handle($report, $manager);

    $fuel = EquipmentFuelTransaction::query()->where('daily_site_report_equipment_line_id', $line->id)->firstOrFail();
    expect($fuel->exception_status)->toBe('insufficient_evidence')
        ->and($fuel->exception_reason)->toContain('Comparable');
});
