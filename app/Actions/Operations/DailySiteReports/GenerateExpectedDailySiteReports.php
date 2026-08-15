<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\ExpectedDailySiteReport;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\ReportingCalendarResolver;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;

final readonly class GenerateExpectedDailySiteReports
{
    public function __construct(
        private TenantContext $tenantContext,
        private ReportingCalendarResolver $calendarResolver,
        private AuditLogger $auditLogger,
    ) {
        //
    }

    public function handle(CarbonImmutable $from, CarbonImmutable $to, ?string $tenantId = null, ?string $siteId = null): int
    {
        $created = 0;
        $tenants = Tenant::query()
            ->active()
            ->when($tenantId, fn ($query, string $id) => $query->whereKey($id))
            ->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);
            $sites = Site::query()
                ->with(['project', 'manager'])
                ->where('status', 'active')
                ->when($siteId, fn ($query, string $id) => $query->whereKey($id))
                ->get();

            foreach ($sites as $site) {
                for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) {
                    if (! $this->calendarResolver->isReportingDay($site, $date)) {
                        continue;
                    }

                    $expected = ExpectedDailySiteReport::query()->firstOrCreate(
                        [
                            'tenant_id' => $site->tenant_id,
                            'site_id' => $site->id,
                            'report_date' => $date->startOfDay(),
                        ],
                        [
                            'branch_id' => $site->branch_id,
                            'project_id' => $site->project_id,
                            'deadline_at' => $this->calendarResolver->deadlineAt($site, $date),
                            'status' => ExpectedDailySiteReport::STATUS_EXPECTED,
                        ],
                    );

                    if (! $expected->wasRecentlyCreated) {
                        continue;
                    }

                    $created++;
                    $this->auditLogger->record(
                        'operations.expected_dsr.generated',
                        $expected,
                        properties: ['site_id' => $site->id, 'report_date' => $date->toDateString()],
                    );
                }
            }
        }

        return $created;
    }
}
