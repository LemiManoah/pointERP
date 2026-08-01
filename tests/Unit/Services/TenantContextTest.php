<?php

declare(strict_types=1);

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;

it('resolves the current tenant from the authenticated user', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user);

    expect(resolve(TenantContext::class)->current()->is($tenant))->toBeTrue()
        ->and(resolve(TenantContext::class)->id())->toBe($tenant->id);
});

it('fails safely when no tenant can be resolved', function (): void {
    resolve(TenantContext::class)->forget();

    resolve(TenantContext::class)->current();
})->throws(MissingTenantContextException::class);

it('rejects inactive tenants', function (): void {
    $tenant = Tenant::factory()->inactive()->create();

    resolve(TenantContext::class)->set($tenant);
})->throws(MissingTenantContextException::class);
