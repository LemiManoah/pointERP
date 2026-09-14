<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceShift;
use App\Enums\AttendanceStatus;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportLabourLine;
use App\Models\Project;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\DsrLabourAttendanceComparison;
use Illuminate\Database\Seeder;

final class WorkforceDsrDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
        $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
        $trade = WorkforceTrade::query()->where('tenant_id', $project->tenant_id)->where('code', 'GENERAL-LABOURER')->firstOrFail();

        $reports = DailySiteReport::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [
                DailySiteReport::STATUS_SUBMITTED,
                DailySiteReport::STATUS_APPROVED,
                DailySiteReport::STATUS_RETURNED,
            ])
            ->oldest('report_date')
            ->get();

        foreach ($reports as $report) {
            $line = DailySiteReportLabourLine::query()->where('daily_site_report_id', $report->id)->first();

            if (! $line instanceof DailySiteReportLabourLine) {
                continue;
            }

            $line->update([
                'workforce_trade_id' => $trade->id,
                'trade_or_role' => $trade->name,
            ]);

            $register = SiteAttendanceRegister::query()->updateOrCreate(
                [
                    'tenant_id' => $report->tenant_id,
                    'site_id' => $report->site_id,
                    'attendance_date' => $report->report_date->toDateString(),
                    'shift' => AttendanceShift::Day->value,
                ],
                [
                    'branch_id' => $report->branch_id,
                    'project_id' => $report->project_id,
                    'status' => AttendanceRegisterStatus::Confirmed,
                    'notes' => 'Confirmed attendance supporting '.$report->reference.'.',
                    'recorded_by' => $actor->id,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => $report->report_date->copy()->setTime(18, 0),
                ],
            );

            $attendanceHours = match ($report->status) {
                DailySiteReport::STATUS_APPROVED => max(0, (float) $line->hours - 1),
                DailySiteReport::STATUS_RETURNED => (float) $line->hours + 1,
                default => (float) $line->hours,
            };

            $register->records()->delete();
            $subcontractorId = $line->subcontractor_id;
            $subcontractorName = is_string($subcontractorId)
                ? Customer::query()->whereKey($subcontractorId)->value('name')
                : null;
            $register->records()->create([
                'tenant_id' => $report->tenant_id,
                'branch_id' => $report->branch_id,
                'staff_id' => null,
                'subcontractor_id' => $subcontractorId,
                'workforce_trade_id' => $trade->id,
                'labour_source' => $line->labour_source,
                'worker_name_snapshot' => null,
                'subcontractor_name_snapshot' => is_string($subcontractorName) ? $subcontractorName : null,
                'headcount' => $line->headcount,
                'attendance_status' => AttendanceStatus::Present,
                'regular_hours_per_person' => $attendanceHours,
                'overtime_hours_per_person' => 0,
                'notes' => match ($report->status) {
                    DailySiteReport::STATUS_APPROVED => 'Demo attendance intentionally below reported labour for the override scenario.',
                    DailySiteReport::STATUS_RETURNED => 'Demo attendance intentionally above reported labour for the under-reporting scenario.',
                    default => 'Demo attendance aligned with reported labour.',
                },
            ]);

            if ($report->isApproved()) {
                $comparison = resolve(DsrLabourAttendanceComparison::class)->compare($report);
                $checkedAt = $report->approved_at ?? now();
                $report->forceFill([
                    'labour_attendance_status' => $comparison['status'],
                    'labour_attendance_snapshot' => [
                        ...$comparison,
                        'checked_at' => $checkedAt->toIso8601String(),
                        'override_reason' => 'Approved quarry production record after supervisor verified the additional crew hours.',
                        'override_by' => $actor->id,
                    ],
                    'labour_attendance_checked_at' => $checkedAt,
                    'labour_attendance_override_reason' => 'Approved quarry production record after supervisor verified the additional crew hours.',
                    'labour_attendance_override_by' => $actor->id,
                ])->save();
            }
        }
    }
}
