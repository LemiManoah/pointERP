<?php

declare(strict_types=1);

use App\Actions\Operations\DailySiteReports\PostApprovedDsrEquipmentLines;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DsrEquipmentLineAdjustment;
use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentUsageLog;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\EquipmentScopeSummary;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('appends approved DSR fleet corrections without rewriting original ledgers', function (): void {
    $requester = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $approver = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $site = Site::query()->where('reference', 'KIBOGA-HOIMA')->firstOrFail();
    $equipment = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $report = DailySiteReport::query()->create([
        'tenant_id' => $site->tenant_id,
        'branch_id' => $site->branch_id,
        'project_id' => $site->project_id,
        'site_id' => $site->id,
        'report_date' => now()->subDay()->toDateString(),
        'reference' => 'DSR-FLEET-CORRECTION-001',
        'work_summary' => 'Compaction shift requiring a later signed correction.',
        'status' => DailySiteReport::STATUS_APPROVED,
        'submitted_by' => $requester->id,
        'submitted_at' => now()->subHours(3),
        'approved_by' => $approver->id,
        'approved_at' => now()->subHours(2),
        'created_by' => $requester->id,
        'updated_by' => $approver->id,
    ]);
    $line = DailySiteReportEquipmentLine::query()->create([
        'tenant_id' => $report->tenant_id,
        'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id,
        'equipment_id' => $equipment->id,
        'equipment_name' => $equipment->name,
        'equipment_identifier' => $equipment->asset_code,
        'status' => 'working',
        'working_hours' => '10.0000',
        'idle_hours' => '2.0000',
        'fuel_type' => 'diesel',
        'fuel_quantity' => '300.0000',
        'fuel_transaction_type' => 'consumption',
        'fleet_posting_status' => 'unposted',
    ]);

    resolve(PostApprovedDsrEquipmentLines::class)->handle($report, $approver);
    $originalUsage = EquipmentUsageLog::query()->where('daily_site_report_equipment_line_id', $line->id)->firstOrFail();
    $originalFuel = EquipmentFuelTransaction::query()->where('daily_site_report_equipment_line_id', $line->id)->firstOrFail();

    $this->actingAs($requester)
        ->post(route('daily-site-reports.corrections.store', $report), [
            'reason' => 'Signed operator sheet corrected the shift totals.',
            'changes' => [
                'equipment_adjustments' => [[
                    'line_id' => $line->id,
                    'working_hours_delta' => '-2.0000',
                    'idle_hours_delta' => '1.0000',
                    'fuel_quantity_delta' => '-50.0000',
                    'note' => 'Duplicate two-hour and fifty-litre entries removed.',
                ]],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('daily-site-reports.show', $report));

    $correction = DailySiteReportCorrection::query()->where('daily_site_report_id', $report->id)->latest()->firstOrFail();
    $this->actingAs($approver)
        ->post(route('daily-site-reports.corrections.approve', [$report, $correction]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('daily-site-reports.show', $report));

    $adjustment = DsrEquipmentLineAdjustment::query()->where('daily_site_report_correction_id', $correction->id)->firstOrFail();
    expect($correction->refresh()->status)->toBe(DailySiteReportCorrection::STATUS_APPROVED)
        ->and($line->refresh()->working_hours)->toBe('10.0000')
        ->and($line->fuel_quantity)->toBe('300.0000')
        ->and($originalUsage->refresh()->working_hours)->toBe('10.0000')
        ->and($originalFuel->refresh()->quantity)->toBe('300.0000')
        ->and($adjustment->working_hours_delta)->toBe('-2.0000')
        ->and($adjustment->idle_hours_delta)->toBe('1.0000')
        ->and($adjustment->fuel_quantity_delta)->toBe('-50.0000')
        ->and($adjustment->usageLog?->working_hours)->toBe('-2.0000')
        ->and($adjustment->fuelTransaction?->quantity)->toBe('-50.0000');

    $project = Project::query()->findOrFail($report->project_id);
    $fleet = resolve(EquipmentScopeSummary::class)->forProject($project, $requester);
    $row = collect($fleet['reconciliation'])->firstWhere('id', $line->id);

    expect($row)->toBeArray()
        ->and($row['working_hours'])->toBe('8.0000')
        ->and($row['fuel_quantity'])->toBe('250.0000')
        ->and($row['adjustment_count'])->toBe(1);

    $this->actingAs($approver)
        ->post(route('daily-site-reports.corrections.approve', [$report, $correction]))
        ->assertForbidden();

    expect(DsrEquipmentLineAdjustment::query()->where('daily_site_report_correction_id', $correction->id)->count())->toBe(1);
});

it('rejects a correction that would make a fleet total negative', function (): void {
    $requester = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $approver = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    [$report, $line] = createPostedFleetLine($requester, $approver, 'DSR-FLEET-CORRECTION-NEGATIVE');
    $correction = DailySiteReportCorrection::query()->create([
        'tenant_id' => $report->tenant_id,
        'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id,
        'requested_by' => $requester->id,
        'status' => DailySiteReportCorrection::STATUS_SUBMITTED,
        'reason' => 'Invalid negative-total scenario.',
        'old_values' => ['equipment_adjustments' => []],
        'new_values' => ['equipment_adjustments' => [[
            'line_id' => $line->id,
            'working_hours_delta' => '-999999.0000',
            'idle_hours_delta' => '0',
            'fuel_quantity_delta' => '0',
        ]]],
    ]);

    $this->actingAs($approver)
        ->post(route('daily-site-reports.corrections.approve', [$report, $correction]))
        ->assertSessionHasErrors('changes.equipment_adjustments');

    expect($correction->refresh()->status)->toBe(DailySiteReportCorrection::STATUS_SUBMITTED)
        ->and(DsrEquipmentLineAdjustment::query()->where('daily_site_report_correction_id', $correction->id)->exists())->toBeFalse();
});

/** @return array{DailySiteReport, DailySiteReportEquipmentLine} */
function createPostedFleetLine(User $requester, User $approver, string $reference): array
{
    $site = Site::query()->where('reference', 'KIBOGA-HOIMA')->firstOrFail();
    $equipment = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $report = DailySiteReport::query()->create([
        'tenant_id' => $site->tenant_id,
        'branch_id' => $site->branch_id,
        'project_id' => $site->project_id,
        'site_id' => $site->id,
        'report_date' => now()->subDay()->toDateString(),
        'reference' => $reference,
        'status' => DailySiteReport::STATUS_APPROVED,
        'submitted_by' => $requester->id,
        'submitted_at' => now()->subHours(3),
        'approved_by' => $approver->id,
        'approved_at' => now()->subHours(2),
        'created_by' => $requester->id,
        'updated_by' => $approver->id,
    ]);
    $line = DailySiteReportEquipmentLine::query()->create([
        'tenant_id' => $report->tenant_id,
        'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id,
        'equipment_id' => $equipment->id,
        'equipment_name' => $equipment->name,
        'equipment_identifier' => $equipment->asset_code,
        'status' => 'working',
        'working_hours' => '10.0000',
        'idle_hours' => '2.0000',
        'fuel_type' => 'diesel',
        'fuel_quantity' => '300.0000',
        'fuel_transaction_type' => 'consumption',
        'fleet_posting_status' => 'unposted',
    ]);
    resolve(PostApprovedDsrEquipmentLines::class)->handle($report, $approver);

    return [$report, $line->refresh()];
}
