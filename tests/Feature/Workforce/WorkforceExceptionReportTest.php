<?php

declare(strict_types=1);

use App\Actions\Workforce\ProcessWorkforceExceptions;
use App\Models\Project;
use App\Models\User;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\QuarryDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkforceAttendanceDemoSeeder;
use Database\Seeders\WorkforceDemoSeeder;
use Database\Seeders\WorkforceDsrDemoSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(QuarryDemoSeeder::class);
    $this->seed(WorkforceDemoSeeder::class);
    $this->seed(WorkforceAttendanceDemoSeeder::class);
    $this->seed(WorkforceDsrDemoSeeder::class);

    resolve(TenantContext::class)->set(
        User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant,
    );
});

it('shows scoped attendance and DSR labour exceptions to a report viewer', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();

    $this->actingAs($administrator)
        ->get(route('workforce.exceptions.index', [
            'project_id' => $project->id,
            'date_from' => now()->subYears(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/workforce/exceptions/index')
            ->where('summary.total', 3)
            ->where('summary.unconfirmed', 1)
            ->where('summary.labour_variance', 2)
            ->has('exceptions', 3)
            ->has('projects'));
});

it('forbids workforce exception reports without the reporting permission', function (): void {
    $engineer = User::query()->where('email', 'luate@gmail.com')->firstOrFail();

    $this->actingAs($engineer)
        ->get(route('workforce.exceptions.index'))
        ->assertForbidden();
});

it('deduplicates overdue attendance notifications across repeated processing', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $action = resolve(ProcessWorkforceExceptions::class);
    $asOf = CarbonImmutable::now()->endOfDay();

    $first = $action->handle($asOf, $administrator->tenant_id);
    $second = $action->handle($asOf, $administrator->tenant_id);
    $notifications = $administrator->notifications()
        ->get()
        ->filter(fn (DatabaseNotification $notification): bool => str_starts_with((string) ($notification->data['alert_key'] ?? ''), 'workforce:attendance:'));

    expect($first['overdue_registers'])->toBe(1)
        ->and($first['notifications'])->toBeGreaterThan(0)
        ->and($second['notifications'])->toBe(0)
        ->and($notifications)->toHaveCount(1);
});
