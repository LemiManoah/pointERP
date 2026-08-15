<?php

declare(strict_types=1);

use App\Actions\Operations\DailySiteReports\ProcessOverdueDailySiteReports;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('dsr:mark-missing {--date=} {--tenant=} {--site=}', function (ProcessOverdueDailySiteReports $action): int {
    $asOf = $this->option('date')
        ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay()
        : CarbonImmutable::now();
    $tenant = $this->option('tenant');
    $site = $this->option('site');
    $result = $action->handle(
        $asOf,
        is_string($tenant) && $tenant !== '' ? $tenant : null,
        is_string($site) && $site !== '' ? $site : null,
    );

    $this->info(sprintf('Marked %d obligation(s) missing.', $result['missing']));

    return 0;
})->purpose('Compatibility alias for the Phase 2C missing-report command.');

Schedule::command('dsr:generate-expected')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('dsr:process-overdue')->hourly()->withoutOverlapping();
Schedule::command('documents:notify-expiring')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('notifications:send-digests')->dailyAt('07:15')->withoutOverlapping();
