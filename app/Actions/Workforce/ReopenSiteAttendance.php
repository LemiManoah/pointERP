<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\AttendanceRegisterStatus;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\WorkforceExceptionNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReopenSiteAttendance
{
    public function __construct(
        private AuditLogger $auditLogger,
        private WorkforceExceptionNotificationService $notifications,
    ) {}

    public function handle(SiteAttendanceRegister $register, User $actor, string $reason): SiteAttendanceRegister
    {
        return DB::transaction(function () use ($actor, $reason, $register): SiteAttendanceRegister {
            $locked = SiteAttendanceRegister::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AttendanceRegisterStatus::Confirmed) {
                throw ValidationException::withMessages(['register' => 'Only confirmed attendance can be reopened.']);
            }

            $oldValues = $locked->only(['status', 'confirmed_by', 'confirmed_at', 'reopened_by', 'reopened_at', 'reopen_reason']);
            $locked->update([
                'status' => AttendanceRegisterStatus::Draft,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ]);
            $this->auditLogger->record(
                'workforce.attendance.reopened',
                $locked,
                $actor,
                $oldValues,
                $locked->fresh()?->toArray() ?? [],
                $reason,
                $locked->branch,
            );

            DB::afterCommit(fn (): int => $this->notifications->attendanceReopened($locked));

            return $locked;
        });
    }
}
