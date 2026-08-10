<?php

declare(strict_types=1);

use App\Models\AuditActivity;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    resolve(TenantContext::class)->set($director->tenant);
});

it('lets authorised users view the tenant audit trail', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $role = Role::query()->where('name', 'Accountant')->firstOrFail();

    AuditActivity::query()->create([
        'log_name' => 'audit',
        'description' => 'access.role.updated',
        'event' => 'access.role.updated',
        'tenant_id' => $director->tenant_id,
        'subject_type' => $role->getMorphClass(),
        'subject_id' => $role->id,
        'causer_type' => $director->getMorphClass(),
        'causer_id' => $director->id,
        'attribute_changes' => [
            'old' => ['name' => 'Accountant'],
            'attributes' => ['name' => 'Senior Accountant'],
        ],
    ]);

    $this->actingAs($director)
        ->get(route('audit-trail.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trail/index')
            ->has('activities', 1)
            ->where('activities.0.event', 'access.role.updated')
            ->where('activities.0.actor_email', 'lemi@gmail.com'));
});

it('filters audit trail entries by event actor branch record type and search', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $role = Role::query()->where('name', 'Accountant')->firstOrFail();
    $branch = $director->branches()->firstOrFail();

    AuditActivity::query()->create([
        'log_name' => 'audit',
        'description' => 'access.role.updated',
        'event' => 'access.role.updated',
        'tenant_id' => $director->tenant_id,
        'branch_id' => $branch->id,
        'subject_type' => $role->getMorphClass(),
        'subject_id' => $role->id,
        'causer_type' => $director->getMorphClass(),
        'causer_id' => $director->id,
        'attribute_changes' => [
            'old' => ['name' => 'Accountant'],
            'attributes' => ['name' => 'Senior Accountant'],
        ],
    ]);
    AuditActivity::query()->create([
        'log_name' => 'audit',
        'description' => 'currency.exchange_rate.approved',
        'event' => 'currency.exchange_rate.approved',
        'tenant_id' => $director->tenant_id,
        'subject_type' => $director->getMorphClass(),
        'subject_id' => $director->id,
        'causer_type' => $director->getMorphClass(),
        'causer_id' => $director->id,
        'attribute_changes' => [
            'old' => ['status' => 'draft'],
            'attributes' => ['status' => 'approved'],
        ],
    ]);

    $this->actingAs($director)
        ->get(route('audit-trail.index', [
            'search' => 'role',
            'event' => 'access.role.updated',
            'branch_id' => $branch->id,
            'actor_id' => $director->id,
            'subject_type' => $role->getMorphClass(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trail/index')
            ->has('activities', 1)
            ->where('activities.0.event', 'access.role.updated')
            ->where('filters.search', 'role'));
});

it('forbids users without audit trail permission', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    resolve(TenantContext::class)->set($storeKeeper->tenant);

    $this->actingAs($storeKeeper)
        ->get(route('audit-trail.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('errors/show')
            ->where('status', 403));
});
