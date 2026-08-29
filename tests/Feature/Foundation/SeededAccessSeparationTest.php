<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Staff;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('forbids operational users from access control pages', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    $this->actingAs($storeKeeper)
        ->get(route('access-control.users.index'))
        ->assertForbidden();
});

it('limits a branch administrator to users in their branch', function (): void {
    $administrator = User::query()->where('email', 'admin.kla@point.test')->firstOrFail();

    $this->actingAs($administrator)
        ->get(route('access-control.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('access-control/users/index')
            ->has('users', 8)
            ->where('branches.0.code', 'KLA-HQ'));
});

it('prevents a branch administrator from assigning staff in another branch', function (): void {
    $administrator = User::query()->where('email', 'admin.kla@point.test')->firstOrFail();
    $guluStaff = Staff::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $guluBranch = Branch::query()->where('code', 'GUL-SITE')->firstOrFail();

    $this->actingAs($administrator)
        ->post(route('access-control.users.store'), [
            'staff_id' => $guluStaff->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['Project Manager'],
            'permissions' => [],
            'branch_ids' => [$guluBranch->id],
            'default_branch_id' => $guluBranch->id,
            'is_active' => true,
            'is_director' => false,
        ])
        ->assertSessionHasErrors(['staff_id', 'branch_ids.0']);
});
