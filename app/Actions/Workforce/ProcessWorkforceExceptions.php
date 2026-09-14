<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Enums\AttendanceRegisterStatus;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\Tenant;
use App\Services\ReportingCalendarResolver;
use App\Services\TenantContext;
use App\Services\WorkforceExceptionNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class ProcessWorkforceExceptions
{
    public function __construct(
        private ReportingCalendarResolver $calendarResolver,
        private TenantContext $tenantContext,
        private WorkforceExceptionNotificationService $notifications,
    ) {}

    /** @return array{overdue_registers: int, notifications: int} */
    public function handle(CarbonImmutable $asOf, ?string $tenantId = null): array
    {
        $result = ['overdue_registers' => 0, 'notifications' => 0];
        $tenants = Tenant::query()
            ->active()
            ->when($tenantId, fn (Builder $query, string $id): Builder => $query->whereKey($id))
            ->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);

            $registers = SiteAttendanceRegister::query()
                ->with(['recorder', 'site.manager', 'site.project'])
                ->where('status', AttendanceRegisterStatus::Draft->value)
                ->whereDate('attendance_date', '<=', $asOf->toDateString())
                ->get();

            foreach ($registers as $register) {
                if (! $register->site instanceof Site) {
                    continue;
                }

                if ($this->calendarResolver->deadlineAt($register->site, $register->attendance_date)->greaterThan($asOf)) {
                    continue;
                }

                $result['overdue_registers']++;
                $result['notifications'] += $this->notifications->attendanceOverdue($register);
            }
        }

        return $result;
    }
}
