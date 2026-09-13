<?php

declare(strict_types=1);

use App\Enums\DsrLabourAttendanceStatus;
use App\Models\AuditActivity;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportLabourLine;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use App\Services\DsrLabourAttendanceComparison;
use App\Services\TenantContext;
use Database\Seeders\QuarryDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkforceAttendanceDemoSeeder;
use Database\Seeders\WorkforceDemoSeeder;
use Database\Seeders\WorkforceDsrDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(QuarryDemoSeeder::class);
    $this->seed(WorkforceDemoSeeder::class);
    $this->seed(WorkforceAttendanceDemoSeeder::class);
    $this->seed(WorkforceDsrDemoSeeder::class);

    resolve(TenantContext::class)->set(
        User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant,
    );
});

it('shows confirmed attendance against DSR labour by workforce trade', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_SUBMITTED)->firstOrFail();

    $this->actingAs($administrator)
        ->get(route('daily-site-reports.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/daily-site-reports/show')
            ->where('labourAttendance.status', DsrLabourAttendanceStatus::Matched->value)
            ->where('labourAttendance.attendance_available', true)
            ->where('labourAttendance.variance_hours', 0)
            ->has('labourAttendance.groups', 1)
            ->has('workforceTrades'));
});

it('blocks an over-reported DSR when the approver lacks override authority', function (): void {
    $projectManager = User::query()->where('email', 'latif@gmail.com')->firstOrFail();
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_SUBMITTED)->firstOrFail();
    $line = DailySiteReportLabourLine::query()->where('daily_site_report_id', $report->id)->firstOrFail();
    $line->update(['hours' => '9.0000']);

    expect($projectManager->can('daily-site-reports.override-labour-variance'))->toBeFalse();

    $this->actingAs($projectManager)
        ->post(route('daily-site-reports.approve', $report))
        ->assertSessionHasErrors('labour_variance_override_reason');

    expect($report->refresh()->status)->toBe(DailySiteReport::STATUS_SUBMITTED);
});

it('allows a permission holder to approve an over-reported DSR with an audited reason', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_SUBMITTED)->firstOrFail();
    $line = DailySiteReportLabourLine::query()->where('daily_site_report_id', $report->id)->firstOrFail();
    $line->update(['hours' => '9.0000']);
    $report->materialLines()->delete();
    $reason = 'The confirmed day register omitted an authorized late loading crew.';

    $this->actingAs($administrator)
        ->post(route('daily-site-reports.approve', $report), [
            'labour_variance_override_reason' => $reason,
        ])
        ->assertRedirect(route('daily-site-reports.show', $report));

    expect($report->refresh()->status)->toBe(DailySiteReport::STATUS_APPROVED)
        ->and($report->labour_attendance_status)->toBe(DsrLabourAttendanceStatus::OverReported)
        ->and($report->labour_attendance_override_reason)->toBe($reason)
        ->and($report->labour_attendance_snapshot)->toBeArray()
        ->and(AuditActivity::query()
            ->where('event', 'operations.daily_site_report.labour_variance_overridden')
            ->where('subject_id', $report->id)
            ->exists())->toBeTrue();
});

it('warns about under-reporting and missing attendance without blocking the comparison', function (): void {
    $report = DailySiteReport::query()->where('status', DailySiteReport::STATUS_SUBMITTED)->firstOrFail();
    $line = DailySiteReportLabourLine::query()->where('daily_site_report_id', $report->id)->firstOrFail();
    $comparison = resolve(DsrLabourAttendanceComparison::class);

    $line->update(['hours' => '7.0000']);
    expect($comparison->compare($report)['status'])->toBe(DsrLabourAttendanceStatus::UnderReported->value);

    SiteAttendanceRegister::query()
        ->where('site_id', $report->site_id)
        ->whereDate('attendance_date', $report->report_date->toDateString())
        ->delete();

    expect($comparison->compare($report)['status'])->toBe(DsrLabourAttendanceStatus::MissingAttendance->value);
});
