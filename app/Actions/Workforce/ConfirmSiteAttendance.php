<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\AttendanceRegisterStatus;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmSiteAttendance
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(SiteAttendanceRegister $register, User $actor): SiteAttendanceRegister
    {
        return DB::transaction(function () use ($actor, $register): SiteAttendanceRegister {
            $locked = SiteAttendanceRegister::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['register' => 'This attendance register is already confirmed.']);
            }

            if (! $locked->records()->exists()) {
                throw ValidationException::withMessages(['register' => 'Add at least one attendance line before confirming.']);
            }

            $oldValues = $locked->only(['status', 'confirmed_by', 'confirmed_at']);
            $locked->update([
                'status' => AttendanceRegisterStatus::Confirmed,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
            $this->auditLogger->record(
                'workforce.attendance.confirmed',
                $locked,
                $actor,
                $oldValues,
                $locked->fresh()?->toArray() ?? [],
                branch: $locked->branch,
            );

            return $locked;
        });
    }
}
