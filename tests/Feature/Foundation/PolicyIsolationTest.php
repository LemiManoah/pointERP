<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\ExchangeRate;
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

it('keeps branch administrators inside their assigned branch policy boundary', function (): void {
    $administrator = User::query()->where('email', 'admin.kla@point.test')->firstOrFail();
    $kampalaBranch = Branch::query()->where('code', 'KLA-HQ')->firstOrFail();
    $guluBranch = Branch::query()->where('code', 'GUL-SITE')->firstOrFail();

    expect($administrator->can('view', $kampalaBranch))->toBeTrue()
        ->and($administrator->can('view', $guluBranch))->toBeFalse()
        ->and($administrator->can('update', $kampalaBranch))->toBeFalse();
});

it('allows directors to approve tenant-wide draft exchange rates', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $tenant = Tenant::query()->where('code', 'POINT')->firstOrFail();

    $draftRate = ExchangeRate::query()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => null,
        'from_currency_code' => 'USD',
        'to_currency_code' => 'UGX',
        'rate' => '3700.0000000000',
        'effective_date' => now()->toDateString(),
        'source' => 'manual',
        'status' => ExchangeRate::STATUS_DRAFT,
        'created_by' => $director->id,
        'updated_by' => $director->id,
    ]);

    expect($director->can('approve', $draftRate))->toBeTrue();
});

it('prevents branch users from creating tenant-wide exchange rates', function (): void {
    $accountant = User::query()->where('email', 'accountant.juba@point.test')->firstOrFail();

    $this->actingAs($accountant)
        ->post(route('foundation.exchange-rates.store'), [
            'branch_id' => '__tenant__',
            'from_currency_code' => 'USD',
            'to_currency_code' => 'UGX',
            'rate' => '3700',
            'effective_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('branch_id');
});

it('prevents users from deactivating their own account', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($director)
        ->delete(route('access-control.users.destroy', $director))
        ->assertForbidden();

    expect($director->refresh()->is_active)->toBeTrue();
});

it('prevents users from making their own account inactive through edit', function (): void {
    $director = User::query()
        ->with(['branches', 'roles', 'permissions'])
        ->where('email', 'lemi@gmail.com')
        ->firstOrFail();

    $this->actingAs($director)
        ->put(route('access-control.users.update', $director), [
            'staff_id' => $director->staff_id,
            'password' => null,
            'password_confirmation' => null,
            'is_active' => false,
            'is_director' => $director->is_director,
            'roles' => $director->getRoleNames()->all(),
            'permissions' => $director->getDirectPermissions()->pluck('name')->values()->all(),
            'branch_ids' => $director->branches->pluck('id')->values()->all(),
            'default_branch_id' => $director->branches()
                ->wherePivot('is_default', true)
                ->value('branches.id'),
        ])
        ->assertSessionHasErrors('is_active');

    expect($director->refresh()->is_active)->toBeTrue();
});
