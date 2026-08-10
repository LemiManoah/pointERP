<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
});

function pointUser(): User
{
    return User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
}

function pointTenant(): Tenant
{
    return Tenant::query()->where('code', 'POINT')->firstOrFail();
}

it('prevents creating a branch exchange rate for an inaccessible branch', function (): void {
    $tenant = pointTenant();
    $homeBranch = Branch::query()
        ->where('tenant_id', $tenant->id)
        ->where('code', 'KLA-HQ')
        ->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->syncRoles([Role::query()->where('name', 'Administrator')->firstOrFail()]);
    $user->branches()->attach($homeBranch->id, ['is_default' => true]);

    resolve(TenantContext::class)->set($tenant);

    $branch = Branch::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Unassigned Branch',
        'code' => 'UNASSIGNED',
        'country_code' => 'UG',
        'default_currency_code' => 'UGX',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->post(route('foundation.exchange-rates.store'), [
            'branch_id' => $branch->id,
            'from_currency_code' => 'USD',
            'to_currency_code' => 'UGX',
            'rate' => '3700',
            'effective_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('branch_id');

    expect(ExchangeRate::query()->where('branch_id', $branch->id)->exists())->toBeFalse();
});

it('approves a draft exchange rate and supersedes older approved rates', function (): void {
    $tenant = pointTenant();
    $user = pointUser();

    resolve(TenantContext::class)->set($tenant);

    $oldRate = ExchangeRate::query()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'from_currency_code' => 'USD',
        'to_currency_code' => 'UGX',
        'rate' => '3600.0000000000',
        'effective_date' => now()->subDay()->toDateString(),
        'source' => 'manual',
        'status' => ExchangeRate::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now()->subDay(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $draftRate = ExchangeRate::query()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'from_currency_code' => 'USD',
        'to_currency_code' => 'UGX',
        'rate' => '3700.0000000000',
        'effective_date' => now()->toDateString(),
        'source' => 'manual',
        'status' => ExchangeRate::STATUS_DRAFT,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('foundation.exchange-rates.approve', $draftRate))
        ->assertRedirect();

    expect($draftRate->refresh()->status)->toBe(ExchangeRate::STATUS_APPROVED)
        ->and($oldRate->refresh()->status)->toBe(ExchangeRate::STATUS_SUPERSEDED);
});

it('does not disable a tenant currency used as a branch base currency', function (): void {
    $tenant = pointTenant();
    $user = pointUser();

    resolve(TenantContext::class)->set($tenant);

    $this->actingAs($user)
        ->put(route('foundation.currency-settings.tenant.toggle', 'UGX'))
        ->assertRedirect();

    expect(
        TenantCurrency::query()
            ->where('tenant_id', $tenant->id)
            ->where('currency_code', 'UGX')
            ->firstOrFail()
            ->is_enabled,
    )->toBeTrue();
});
