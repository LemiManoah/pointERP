<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DsrLabourSource;
use App\Enums\StaffEmploymentType;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveSiteAttendance
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param array{
     *   project_id: string,
     *   site_id: string,
     *   attendance_date: string,
     *   shift: string,
     *   notes?: string|null,
     *   records: list<array{
     *     labour_source: string,
     *     staff_id?: string|null,
     *     subcontractor_id?: string|null,
     *     workforce_trade_id: string,
     *     worker_name_snapshot?: string|null,
     *     headcount: int,
     *     attendance_status: string,
     *     regular_hours_per_person: numeric-string|int|float,
     *     overtime_hours_per_person: numeric-string|int|float,
     *     notes?: string|null
     *   }>
     * } $data
     */
    public function handle(array $data, User $actor, ?SiteAttendanceRegister $register = null): SiteAttendanceRegister
    {
        $tenantId = $this->tenantContext->id();
        $project = Project::query()->where('tenant_id', $tenantId)->whereKey($data['project_id'])->firstOrFail();
        $site = Site::query()->where('tenant_id', $tenantId)->whereKey($data['site_id'])->firstOrFail();

        if ($site->project_id !== $project->id || $site->branch_id !== $project->branch_id) {
            throw ValidationException::withMessages(['site_id' => 'The selected site does not belong to this project.']);
        }

        if ($register instanceof SiteAttendanceRegister && ! $register->isDraft()) {
            throw ValidationException::withMessages(['register' => 'Confirmed attendance must be reopened before it can be edited.']);
        }

        $duplicate = SiteAttendanceRegister::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $site->id)
            ->whereDate('attendance_date', $data['attendance_date'])
            ->where('shift', $data['shift'])
            ->when($register instanceof SiteAttendanceRegister, fn (Builder $query) => $query->where('id', '!=', $register->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['attendance_date' => 'Attendance already exists for this site, date and shift.']);
        }

        return DB::transaction(function () use ($actor, $data, $project, $register, $site, $tenantId): SiteAttendanceRegister {
            $oldValues = $register?->load('records')->toArray() ?? [];

            if ($register instanceof SiteAttendanceRegister) {
                $register->update([
                    'project_id' => $project->id,
                    'site_id' => $site->id,
                    'branch_id' => $site->branch_id,
                    'attendance_date' => $data['attendance_date'],
                    'shift' => $data['shift'],
                    'notes' => $data['notes'] ?? null,
                ]);
                $register->records()->delete();
                $event = 'workforce.attendance.updated';
            } else {
                $register = SiteAttendanceRegister::query()->create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $site->branch_id,
                    'project_id' => $project->id,
                    'site_id' => $site->id,
                    'attendance_date' => $data['attendance_date'],
                    'shift' => $data['shift'],
                    'status' => AttendanceRegisterStatus::Draft,
                    'notes' => $data['notes'] ?? null,
                    'recorded_by' => $actor->id,
                ]);
                $event = 'workforce.attendance.created';
            }

            $seenStaff = [];

            foreach ($data['records'] as $index => $line) {
                $record = $this->recordAttributes($line, $site, $tenantId, $index);

                if (is_string($record['staff_id'])) {
                    if (in_array($record['staff_id'], $seenStaff, true)) {
                        throw ValidationException::withMessages([sprintf('records.%d.staff_id', $index) => 'A staff member can appear only once in an attendance register.']);
                    }

                    $seenStaff[] = $record['staff_id'];
                }

                $register->records()->create($record);
            }

            $fresh = $register->fresh(['records']) ?? $register;
            $this->auditLogger->record($event, $register, $actor, $oldValues, $fresh->toArray(), branch: $site->branch);

            return $fresh;
        });
    }

    /**
     * @param array{
     *   labour_source: string,
     *   staff_id?: string|null,
     *   subcontractor_id?: string|null,
     *   workforce_trade_id: string,
     *   worker_name_snapshot?: string|null,
     *   headcount: int,
     *   attendance_status: string,
     *   regular_hours_per_person: numeric-string|int|float,
     *   overtime_hours_per_person: numeric-string|int|float,
     *   notes?: string|null
     * } $line
     * @return array<string, mixed>
     */
    private function recordAttributes(array $line, Site $site, string $tenantId, int $index): array
    {
        $source = DsrLabourSource::from($line['labour_source']);
        $status = AttendanceStatus::from($line['attendance_status']);
        $trade = WorkforceTrade::query()->where('tenant_id', $tenantId)->where('is_active', true)->whereKey($line['workforce_trade_id'])->firstOrFail();
        $regularHours = (float) $line['regular_hours_per_person'];
        $overtimeHours = (float) $line['overtime_hours_per_person'];

        if ($regularHours + $overtimeHours > 24) {
            throw ValidationException::withMessages([sprintf('records.%d.regular_hours_per_person', $index) => 'Regular and overtime hours cannot exceed 24 hours per person.']);
        }

        if (! $status->countsHours() && ($regularHours > 0 || $overtimeHours > 0)) {
            throw ValidationException::withMessages([sprintf('records.%d.attendance_status', $index) => 'Absent or excused attendance must have zero working hours.']);
        }

        if ($source === DsrLabourSource::Subcontractor) {
            $subcontractorId = $line['subcontractor_id'] ?? null;

            if (! is_string($subcontractorId) || $subcontractorId === '') {
                throw ValidationException::withMessages([sprintf('records.%d.subcontractor_id', $index) => 'Select the subcontractor company.']);
            }

            $subcontractor = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('type', Customer::TYPE_SUBCONTRACTOR)
                ->where('status', 'active')
                ->where(function (Builder $query) use ($site): void {
                    $query->whereNull('branch_id')->orWhere('branch_id', $site->branch_id);
                })
                ->whereKey($subcontractorId)
                ->firstOrFail();

            return [
                'tenant_id' => $tenantId,
                'branch_id' => $site->branch_id,
                'staff_id' => null,
                'subcontractor_id' => $subcontractor->id,
                'workforce_trade_id' => $trade->id,
                'labour_source' => $source,
                'worker_name_snapshot' => $line['worker_name_snapshot'] ?? null,
                'subcontractor_name_snapshot' => $subcontractor->name,
                'headcount' => $line['headcount'],
                'attendance_status' => $status,
                'regular_hours_per_person' => $regularHours,
                'overtime_hours_per_person' => $overtimeHours,
                'notes' => $line['notes'] ?? null,
            ];
        }

        $staffId = $line['staff_id'] ?? null;

        if (! is_string($staffId) || $staffId === '') {
            throw ValidationException::withMessages([sprintf('records.%d.staff_id', $index) => 'Select the staff member.']);
        }

        $staff = Staff::query()->where('tenant_id', $tenantId)->where('branch_id', $site->branch_id)->where('status', 'active')->whereKey($staffId)->firstOrFail();
        $expectedSource = $staff->employment_type === StaffEmploymentType::Casual
            ? DsrLabourSource::Casual
            : DsrLabourSource::Internal;

        if ($source !== $expectedSource) {
            throw ValidationException::withMessages([sprintf('records.%d.labour_source', $index) => 'The labour source does not match the staff employment type.']);
        }

        return [
            'tenant_id' => $tenantId,
            'branch_id' => $site->branch_id,
            'staff_id' => $staff->id,
            'subcontractor_id' => null,
            'workforce_trade_id' => $trade->id,
            'labour_source' => $source,
            'worker_name_snapshot' => $staff->name,
            'subcontractor_name_snapshot' => null,
            'headcount' => 1,
            'attendance_status' => $status,
            'regular_hours_per_person' => $regularHours,
            'overtime_hours_per_person' => $overtimeHours,
            'notes' => $line['notes'] ?? null,
        ];
    }
}
