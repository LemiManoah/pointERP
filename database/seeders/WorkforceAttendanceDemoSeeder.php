<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceShift;
use App\Enums\AttendanceStatus;
use App\Enums\DsrLabourSource;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkforceTrade;
use Illuminate\Database\Seeder;

final class WorkforceAttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
        $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
        $pit = Site::query()->where('project_id', $project->id)->where('reference', 'QUARRY-PIT')->firstOrFail();
        $crusherYard = Site::query()->where('project_id', $project->id)->where('reference', 'CRUSHER-YARD')->firstOrFail();
        $engineer = Staff::query()->where('email', 'luate@gmail.com')->firstOrFail();
        $supervisor = Staff::query()->where('email', 'rober@gmail.com')->firstOrFail();
        $engineering = WorkforceTrade::query()->where('tenant_id', $project->tenant_id)->where('code', 'SITE-ENGINEER')->firstOrFail();
        $supervision = WorkforceTrade::query()->where('tenant_id', $project->tenant_id)->where('code', 'QUARRY-SUPERVISOR')->firstOrFail();

        $confirmed = SiteAttendanceRegister::query()->updateOrCreate(
            [
                'tenant_id' => $project->tenant_id,
                'site_id' => $pit->id,
                'attendance_date' => now()->subDay()->toDateString(),
                'shift' => AttendanceShift::Day->value,
            ],
            [
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'status' => AttendanceRegisterStatus::Confirmed,
                'notes' => 'Day shift production and pit preparation.',
                'recorded_by' => $actor->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now()->subDay()->setTime(18, 0),
            ],
        );
        $confirmed->records()->delete();
        $confirmed->records()->create([
            'tenant_id' => $project->tenant_id,
            'branch_id' => $project->branch_id,
            'staff_id' => $engineer->id,
            'subcontractor_id' => null,
            'workforce_trade_id' => $engineering->id,
            'labour_source' => DsrLabourSource::Internal,
            'worker_name_snapshot' => $engineer->name,
            'subcontractor_name_snapshot' => null,
            'headcount' => 1,
            'attendance_status' => AttendanceStatus::Present,
            'regular_hours_per_person' => 8,
            'overtime_hours_per_person' => 1,
            'notes' => 'Supervised drilling and loading.',
        ]);

        $subcontractor = Customer::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('type', Customer::TYPE_SUBCONTRACTOR)
            ->where('status', 'active')
            ->first();

        if ($subcontractor instanceof Customer) {
            $labour = WorkforceTrade::query()->where('tenant_id', $project->tenant_id)->where('code', 'GENERAL-LABOURER')->firstOrFail();
            $confirmed->records()->create([
                'tenant_id' => $project->tenant_id,
                'branch_id' => $project->branch_id,
                'staff_id' => null,
                'subcontractor_id' => $subcontractor->id,
                'workforce_trade_id' => $labour->id,
                'labour_source' => DsrLabourSource::Subcontractor,
                'worker_name_snapshot' => null,
                'subcontractor_name_snapshot' => $subcontractor->name,
                'headcount' => 6,
                'attendance_status' => AttendanceStatus::Present,
                'regular_hours_per_person' => 8,
                'overtime_hours_per_person' => 0,
                'notes' => 'Aggregate loading crew.',
            ]);
        }

        $draft = SiteAttendanceRegister::query()->updateOrCreate(
            [
                'tenant_id' => $project->tenant_id,
                'site_id' => $crusherYard->id,
                'attendance_date' => now()->toDateString(),
                'shift' => AttendanceShift::Night->value,
            ],
            [
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'status' => AttendanceRegisterStatus::Draft,
                'notes' => 'Night shift awaiting supervisor confirmation.',
                'recorded_by' => $actor->id,
                'confirmed_by' => null,
                'confirmed_at' => null,
            ],
        );
        $draft->records()->delete();
        $draft->records()->create([
            'tenant_id' => $project->tenant_id,
            'branch_id' => $project->branch_id,
            'staff_id' => $supervisor->id,
            'subcontractor_id' => null,
            'workforce_trade_id' => $supervision->id,
            'labour_source' => DsrLabourSource::Internal,
            'worker_name_snapshot' => $supervisor->name,
            'subcontractor_name_snapshot' => null,
            'headcount' => 1,
            'attendance_status' => AttendanceStatus::Present,
            'regular_hours_per_person' => 8,
            'overtime_hours_per_person' => 0,
            'notes' => 'Crusher-yard shift supervision.',
        ]);
    }
}
