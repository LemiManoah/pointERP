<?php

declare(strict_types=1);

use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\DailySiteReportReview;
use App\Models\ExpectedDailySiteReport;
use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('separates daily site reports by workflow state on the index', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('daily-site-reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/daily-site-reports/index')
            ->where('summary.pending', 1)
            ->where('summary.returned', 1)
            ->where('summary.approved', 1)
            ->where('summary.missing', 1));
});

it('lets a site engineer submit an assigned draft report with an evidence override reason', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $report = DailySiteReport::query()
        ->where('status', DailySiteReport::STATUS_MISSING)
        ->whereHas('site', fn ($query) => $query->where('reference', 'BUSUNJU'))
        ->firstOrFail();

    $this->actingAs($engineer)
        ->put(route('daily-site-reports.update', $report), [
            'site_id' => $report->site_id,
            'report_date' => $report->report_date->toDateString(),
            'work_summary' => 'Recovered missing report after field upload.',
            'work_lines' => [
                [
                    'boq_item_number' => '31.01(b)(i)',
                    'description' => 'Recovered topsoil quantity',
                    'quantity' => '25',
                    'unit' => 'm3',
                    'rate_amount' => '8500',
                    'currency_code' => 'UGX',
                ],
            ],
        ])
        ->assertRedirect(route('daily-site-reports.show', $report));

    $this->actingAs($engineer)
        ->post(route('daily-site-reports.submit', $report), [
            'evidence_override_reason' => 'Photos will be uploaded after network restoration.',
        ])
        ->assertRedirect(route('daily-site-reports.show', $report));

    expect($report->refresh()->status)->toBe(DailySiteReport::STATUS_SUBMITTED)
        ->and(DailySiteReportReview::query()->where('daily_site_report_id', $report->id)->where('action', DailySiteReportReview::ACTION_SUBMITTED)->exists())->toBeTrue();
});

it('prevents a project manager from approving their own submitted report without override permission', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'KIBOGA-HOIMA')->firstOrFail();

    $report = DailySiteReport::query()->create([
        'tenant_id' => $site->tenant_id,
        'branch_id' => $site->branch_id,
        'project_id' => $site->project_id,
        'site_id' => $site->id,
        'report_date' => '2024-12-11',
        'reference' => 'DSR-KIBOGA-HOIMA-SELF-APPROVAL',
        'work_summary' => 'Self approval test.',
        'status' => DailySiteReport::STATUS_SUBMITTED,
        'submitted_by' => $manager->id,
        'submitted_at' => now(),
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    expect(Gate::forUser($manager)->allows('approve', $report))->toBeFalse();
});

it('lets a project manager return and approve valid submitted reports', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $submittedReport = DailySiteReport::query()->where('reference', 'DSR-BUSUNJU-20241207')->firstOrFail();

    $this->actingAs($manager)
        ->post(route('daily-site-reports.return', $submittedReport), [
            'reason' => 'Attach updated chainage sketch.',
        ])
        ->assertRedirect(route('daily-site-reports.show', $submittedReport));

    expect($submittedReport->refresh()->status)->toBe(DailySiteReport::STATUS_RETURNED)
        ->and($submittedReport->return_reason)->toBe('Attach updated chainage sketch.');
});

it('locks approved reports and records correction requests', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $approvedReport = DailySiteReport::query()->where('reference', 'DSR-BUSUNJU-20241206')->firstOrFail();

    $this->actingAs($manager)
        ->put(route('daily-site-reports.update', $approvedReport), [
            'site_id' => $approvedReport->site_id,
            'report_date' => $approvedReport->report_date->toDateString(),
            'work_summary' => 'Silent edit attempt.',
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('daily-site-reports.corrections.store', $approvedReport), [
            'reason' => 'QS adjusted completion percent.',
            'changes' => ['completion_percent' => '41.0000'],
        ])
        ->assertRedirect(route('daily-site-reports.show', $approvedReport));

    expect(DailySiteReportCorrection::query()
        ->where('daily_site_report_id', $approvedReport->id)
        ->where('reason', 'QS adjusted completion percent.')
        ->exists())->toBeTrue();
});

it('generates expected reports and marks overdue obligations as missing', function (): void {
    Artisan::call('dsr:generate-expected', [
        '--date' => '2024-12-09',
    ]);

    expect(ExpectedDailySiteReport::query()->whereDate('report_date', '2024-12-09')->exists())->toBeTrue();

    Artisan::call('dsr:mark-missing', [
        '--date' => '2024-12-10',
    ]);

    expect(ExpectedDailySiteReport::query()
        ->whereDate('report_date', '2024-12-09')
        ->where('status', ExpectedDailySiteReport::STATUS_MISSING)
        ->exists())->toBeTrue();
});

it('shows linked evidence and workflow trail on the report page', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $report = DailySiteReport::query()->where('reference', 'DSR-BUSUNJU-20241207')->firstOrFail();

    $this->actingAs($engineer)
        ->get(route('daily-site-reports.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/daily-site-reports/show')
            ->has('documents', 2)
            ->has('reviews'));
});
