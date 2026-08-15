<?php

declare(strict_types=1);

use App\Actions\Operations\DailySiteReports\GenerateExpectedDailySiteReports;
use App\Models\ExpectedDailySiteReport;
use App\Models\ReportingCalendar;
use App\Models\Site;
use App\Models\User;
use App\Services\ReportingCalendarResolver;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    resolve(TenantContext::class)->set($director->tenant);
});

it('uses the site calendar before the tenant default', function (): void {
    $site = Site::query()->where('reference', 'BUSUNJU')->firstOrFail();
    $calendar = resolve(ReportingCalendarResolver::class)->calendarFor($site);

    expect($calendar)->toBeInstanceOf(ReportingCalendar::class)
        ->and($calendar?->site_id)->toBe($site->id)
        ->and((string) $calendar?->reporting_deadline)->toStartWith('17:30');
});

it('generates reporting obligations idempotently from the calendar', function (): void {
    $site = Site::query()->where('reference', 'BUSUNJU')->firstOrFail();
    $date = CarbonImmutable::parse('2026-08-17');
    $action = resolve(GenerateExpectedDailySiteReports::class);

    $first = $action->handle($date, $date, $site->tenant_id, $site->id);
    $second = $action->handle($date, $date, $site->tenant_id, $site->id);

    expect($first)->toBe(1)
        ->and($second)->toBe(0)
        ->and(ExpectedDailySiteReport::query()
            ->where('site_id', $site->id)
            ->whereDate('report_date', $date->toDateString())
            ->count())->toBe(1);
});

it('shows only authorised operational exceptions', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('operations-dashboard.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/dashboard/index')
            ->has('summary')
            ->has('rows')
            ->where('canExport', true));
});

it('loads policy-authorised reporting calendar options with complete models', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('reporting-calendars.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/reporting-calendars/index')
            ->has('calendars')
            ->has('projects')
            ->has('sites'));
});

it('records a reason when an authorised user excuses an obligation', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $expected = ExpectedDailySiteReport::query()
        ->where('status', ExpectedDailySiteReport::STATUS_MISSING)
        ->latest('report_date')
        ->firstOrFail();

    $this->actingAs($director)
        ->post(route('expected-daily-site-reports.excuse', $expected), [
            'reason' => 'Site was closed under an approved client instruction.',
        ])
        ->assertRedirect();

    expect($expected->refresh()->status)->toBe(ExpectedDailySiteReport::STATUS_EXCUSED)
        ->and($expected->excuse_reason)->toBe('Site was closed under an approved client instruction.')
        ->and($expected->marked_by)->toBe($director->id);
});

it('prevents users without authority from excusing obligations', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'BUSUNJU')->firstOrFail();
    $expected = ExpectedDailySiteReport::query()
        ->where('site_id', $site->id)
        ->where('status', ExpectedDailySiteReport::STATUS_MISSING)
        ->firstOrFail();

    $this->actingAs($engineer)
        ->post(route('expected-daily-site-reports.excuse', $expected), [
            'reason' => 'Attempted unauthorised compliance adjustment.',
        ])
        ->assertForbidden();
});

it('shows an assigned user only their tenant notifications', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($engineer)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('notifications/index')
            ->has('notifications'));
});
