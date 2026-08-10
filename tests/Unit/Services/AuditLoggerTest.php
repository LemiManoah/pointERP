<?php

declare(strict_types=1);

use App\Models\AuditActivity;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

it('records anonymous tenant audit events', function (): void {
    $tenant = Tenant::factory()->create();

    resolve(AuditLogger::class)->record(
        event: 'tenant.updated',
        subject: $tenant,
        oldValues: ['status' => 'inactive'],
        newValues: ['status' => 'active'],
        reason: 'Initial activation',
    );

    $activity = AuditActivity::query()
        ->where('event', 'tenant.updated')
        ->firstOrFail();

    expect($activity->tenant_id)->toBe($tenant->id)
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->reason)->toBe('Initial activation');
});

it('falls back to tenant context when subject and actor have no tenant id', function (): void {
    $tenant = Tenant::factory()->create();

    resolve(TenantContext::class)->set($tenant);

    resolve(AuditLogger::class)->record(
        event: 'system.checked',
        subject: Role::query()->make(['name' => 'Temporary', 'guard_name' => 'web']),
    );

    $activity = AuditActivity::query()
        ->where('event', 'system.checked')
        ->firstOrFail();

    expect($activity->tenant_id)->toBe($tenant->id)
        ->and($activity->causer_id)->toBeNull();
});

it('records nullable tenant context for system events before tenant resolution', function (): void {
    resolve(AuditLogger::class)->record(
        event: 'system.started',
        subject: Role::query()->make(['name' => 'Temporary', 'guard_name' => 'web']),
    );

    $activity = AuditActivity::query()
        ->where('event', 'system.started')
        ->firstOrFail();

    expect($activity->tenant_id)->toBeNull()
        ->and($activity->causer_id)->toBeNull();
});
