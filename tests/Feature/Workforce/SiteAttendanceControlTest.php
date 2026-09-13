<?php

declare(strict_types=1);

use App\Enums\AttendanceRegisterStatus;
use App\Models\AuditActivity;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\TenantContext;
use Database\Seeders\QuarryDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkforceAttendanceDemoSeeder;
use Database\Seeders\WorkforceDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(QuarryDemoSeeder::class);
    $this->seed(WorkforceDemoSeeder::class);
    $this->seed(WorkforceAttendanceDemoSeeder::class);

    resolve(TenantContext::class)->set(
        User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant,
    );
});

it('shows draft and confirmed attendance to an authorised administrator', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($administrator)
        ->get(route('workforce.attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/workforce/attendance/index')
            ->has('registers', 2)
            ->where('canCreate', true));
});

it('keeps standalone attendance permissions unassigned by default', function (): void {
    $engineer = User::query()->where('email', 'luate@gmail.com')->firstOrFail();

    $this->actingAs($engineer)
        ->get(route('workforce.attendance.index'))
        ->assertForbidden();
});

it('creates a draft attendance register and prevents a duplicate site shift', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
    $site = Site::query()->where('project_id', $project->id)->where('reference', 'QUARRY-PIT')->firstOrFail();
    $staff = Staff::query()->where('email', 'luate@gmail.com')->firstOrFail();
    $trade = WorkforceTrade::query()->where('code', 'SITE-ENGINEER')->firstOrFail();
    $payload = [
        'project_id' => $project->id,
        'site_id' => $site->id,
        'attendance_date' => now()->addDays(2)->toDateString(),
        'shift' => 'day',
        'notes' => 'Test day register.',
        'records' => [[
            'labour_source' => 'internal',
            'staff_id' => $staff->id,
            'subcontractor_id' => null,
            'workforce_trade_id' => $trade->id,
            'worker_name_snapshot' => $staff->name,
            'headcount' => 1,
            'attendance_status' => 'present',
            'regular_hours_per_person' => '8',
            'overtime_hours_per_person' => '1',
            'notes' => null,
        ]],
    ];

    $this->actingAs($administrator)->post(route('workforce.attendance.store'), $payload)->assertRedirect();

    $register = SiteAttendanceRegister::query()->where('attendance_date', $payload['attendance_date'])->firstOrFail();
    expect($register->status)->toBe(AttendanceRegisterStatus::Draft)
        ->and($register->records)->toHaveCount(1)
        ->and($register->records->firstOrFail()->personHours())->toBe(9.0);

    $this->actingAs($administrator)
        ->post(route('workforce.attendance.store'), $payload)
        ->assertSessionHasErrors('attendance_date');
});

it('confirms, locks and reopens attendance through permissions with an audit trail', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $register = SiteAttendanceRegister::query()->where('status', AttendanceRegisterStatus::Draft->value)->firstOrFail();

    $this->actingAs($administrator)
        ->post(route('workforce.attendance.confirm', $register))
        ->assertRedirect(route('workforce.attendance.show', $register));

    expect($register->refresh()->status)->toBe(AttendanceRegisterStatus::Confirmed)
        ->and($register->confirmed_by)->toBe($administrator->id);

    $this->actingAs($administrator)
        ->post(route('workforce.attendance.reopen', $register), [
            'reason' => 'Correct the night shift overtime after supervisor review.',
        ])
        ->assertRedirect(route('workforce.attendance.show', $register));

    expect($register->refresh()->status)->toBe(AttendanceRegisterStatus::Draft)
        ->and($register->reopen_reason)->toBe('Correct the night shift overtime after supervisor review.')
        ->and(AuditActivity::query()->where('event', 'workforce.attendance.confirmed')->where('subject_id', $register->id)->exists())->toBeTrue()
        ->and(AuditActivity::query()->where('event', 'workforce.attendance.reopened')->where('subject_id', $register->id)->exists())->toBeTrue();
});
