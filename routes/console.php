<?php

declare(strict_types=1);

use App\Models\DailySiteReport;
use App\Models\ExpectedDailySiteReport;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

Artisan::command('dsr:generate-expected {--date=} {--from=} {--to=} {--site=} {--tenant=}', function (): int {
    $date = $this->option('date');
    $from = $this->option('from') ?: $date ?: now()->toDateString();
    $to = $this->option('to') ?: $date ?: $from;

    $start = CarbonImmutable::parse((string) $from)->startOfDay();
    $end = CarbonImmutable::parse((string) $to)->startOfDay();
    $created = 0;

    Site::query()
        ->with('project')
        ->where('status', 'active')
        ->when($this->option('site'), fn ($query, string $siteId) => $query->whereKey($siteId))
        ->when($this->option('tenant'), fn ($query, string $tenantId) => $query->where('tenant_id', $tenantId))
        ->chunkById(100, function ($sites) use (&$created, $end, $start): void {
            foreach ($sites as $site) {
                for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                    if ($day->isSunday()) {
                        continue;
                    }

                    $expected = ExpectedDailySiteReport::query()->firstOrCreate(
                        [
                            'tenant_id' => $site->tenant_id,
                            'site_id' => $site->id,
                            'report_date' => $day->toDateString(),
                        ],
                        [
                            'branch_id' => $site->branch_id,
                            'project_id' => $site->project_id,
                            'deadline_at' => expectedDsrDeadlineAt($site, $day),
                            'status' => ExpectedDailySiteReport::STATUS_EXPECTED,
                        ],
                    );

                    if ($expected->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

    $this->info(sprintf('Generated %d expected daily site report obligation(s).', $created));

    return 0;
})->purpose('Generate expected daily site report obligations for active reporting sites.');

Artisan::command('dsr:mark-missing {--date=} {--tenant=}', function (): int {
    $now = $this->option('date')
        ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay()
        : CarbonImmutable::now();
    $marked = 0;

    ExpectedDailySiteReport::query()
        ->with('report')
        ->whereIn('status', [ExpectedDailySiteReport::STATUS_EXPECTED, ExpectedDailySiteReport::STATUS_LATE])
        ->where('deadline_at', '<', $now)
        ->when($this->option('tenant'), fn ($query, string $tenantId) => $query->where('tenant_id', $tenantId))
        ->chunkById(100, function ($expectedReports) use (&$marked): void {
            foreach ($expectedReports as $expected) {
                $report = DailySiteReport::query()
                    ->where('tenant_id', $expected->tenant_id)
                    ->where('site_id', $expected->site_id)
                    ->whereDate('report_date', $expected->report_date->toDateString())
                    ->first();

                if (! $report instanceof DailySiteReport) {
                    $site = Site::query()->find($expected->site_id);

                    if (! $site instanceof Site) {
                        continue;
                    }

                    $report = DailySiteReport::query()->create([
                        'tenant_id' => $expected->tenant_id,
                        'branch_id' => $expected->branch_id,
                        'project_id' => $expected->project_id,
                        'site_id' => $expected->site_id,
                        'report_date' => $expected->report_date,
                        'reference' => 'DSR-'.$site->reference.'-'.$expected->report_date->format('Ymd'),
                        'status' => DailySiteReport::STATUS_MISSING,
                        'expected_at' => $expected->deadline_at,
                    ]);
                }

                if (in_array($report->status, [DailySiteReport::STATUS_DRAFT, DailySiteReport::STATUS_MISSING], true)) {
                    $report->forceFill(['status' => DailySiteReport::STATUS_MISSING])->save();
                }

                $expected->forceFill([
                    'status' => ExpectedDailySiteReport::STATUS_MISSING,
                    'daily_site_report_id' => $report->id,
                    'marked_at' => now(),
                ])->save();

                $marked++;
            }
        });

    $this->info(sprintf('Marked %d expected daily site report obligation(s) as missing.', $marked));

    return 0;
})->purpose('Mark overdue expected daily site report obligations as missing.');

function expectedDsrDeadlineAt(Site $site, CarbonImmutable $day): CarbonImmutable
{
    $site->loadMissing('project');

    $deadline = $site->reporting_deadline ?? $site->project?->reporting_deadline ?? '18:00';
    [$hour, $minute] = array_pad(explode(':', (string) $deadline), 2, '0');

    return $day->setTime((int) $hour, (int) $minute);
}
