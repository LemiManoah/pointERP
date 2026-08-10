<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the custom 404 page', function (): void {
    $this->get('/missing-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('errors/show')
            ->where('status', 404));
});

it('renders the custom 403 page', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    $user = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    resolve(TenantContext::class)->set($user->tenant);

    $this->actingAs($user)
        ->get(route('access-control.users.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('errors/show')
            ->where('status', 403));
});
