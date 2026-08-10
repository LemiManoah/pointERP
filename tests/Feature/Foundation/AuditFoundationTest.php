<?php

declare(strict_types=1);

use App\Actions\AccessControl\Roles\UpdateRole;
use App\Actions\Foundation\ExchangeRates\ApproveExchangeRate;
use App\Models\AuditActivity;
use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    $tenant = Tenant::query()->where('code', 'POINT')->firstOrFail();

    resolve(TenantContext::class)->set($tenant);
});

it('audits role permission changes', function (): void {
    $actor = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $role = Role::query()->where('name', 'Project Manager')->firstOrFail();

    $this->actingAs($actor);

    resolve(UpdateRole::class)->handle($role, [
        'name' => 'Project Lead',
        'permissions' => ['branches.view', 'exchange-rates.view'],
    ]);

    $activity = AuditActivity::query()
        ->where('event', 'access.role.updated')
        ->latest()
        ->firstOrFail();
    $changes = $activity->attribute_changes?->toArray() ?? [];

    expect($activity->tenant_id)->toBe($actor->tenant_id)
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->subject_id)->toBe($role->id)
        ->and(data_get($changes, 'old.name'))->toBe('Project Manager')
        ->and(data_get($changes, 'attributes.name'))->toBe('Project Lead')
        ->and(data_get($changes, 'attributes.permissions'))->toBe(['branches.view', 'exchange-rates.view']);
});

it('audits exchange-rate approval with tenant and branch context', function (): void {
    $actor = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $tenant = Tenant::query()->where('code', 'POINT')->firstOrFail();
    $exchangeRate = ExchangeRate::query()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'from_currency_code' => 'USD',
        'to_currency_code' => 'UGX',
        'rate' => '3700.0000000000',
        'effective_date' => now()->toDateString(),
        'source' => 'manual',
        'status' => ExchangeRate::STATUS_DRAFT,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    $this->actingAs($actor);

    resolve(ApproveExchangeRate::class)->handle($exchangeRate, $actor);

    $activity = AuditActivity::query()
        ->where('event', 'currency.exchange_rate.approved')
        ->latest()
        ->firstOrFail();
    $changes = $activity->attribute_changes?->toArray() ?? [];

    expect($activity->tenant_id)->toBe($tenant->id)
        ->and($activity->branch_id)->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->subject_id)->toBe($exchangeRate->id)
        ->and(data_get($changes, 'old.status'))->toBe(ExchangeRate::STATUS_DRAFT)
        ->and(data_get($changes, 'attributes.status'))->toBe(ExchangeRate::STATUS_APPROVED);
});

it('audits branch currency setting changes from the controller', function (): void {
    $actor = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $branch = Branch::query()->where('code', 'JUB-HQ')->firstOrFail();

    $this->actingAs($actor)
        ->post(route('foundation.currency-settings.branches.store'), [
            'branch_id' => $branch->id,
            'currency_code' => 'USD',
            'is_enabled' => true,
            'is_default_transaction_currency' => true,
            'can_receive' => true,
            'can_pay' => true,
        ])
        ->assertRedirect();

    $activity = AuditActivity::query()
        ->where('event', 'currency.branch_currency.updated')
        ->latest()
        ->firstOrFail();
    $changes = $activity->attribute_changes?->toArray() ?? [];

    expect($activity->tenant_id)->toBe($actor->tenant_id)
        ->and($activity->branch_id)->toBe($branch->id)
        ->and($activity->causer_id)->toBe($actor->id)
        ->and(data_get($changes, 'attributes.currency_code'))->toBe('USD')
        ->and(data_get($changes, 'attributes.is_default_transaction_currency'))->toBeTrue();
});
