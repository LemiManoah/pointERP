<?php

declare(strict_types=1);

use App\Exceptions\MissingTenantContextException;
use App\Exceptions\TenantMismatchException;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantOwnedWidget;

beforeEach(function (): void {
    Schema::create('tenant_owned_widgets', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
        $table->string('name');
    });
});

it('automatically assigns tenant id when creating tenant-owned records', function (): void {
    $tenant = Tenant::factory()->create();
    resolve(TenantContext::class)->set($tenant);

    $widget = TenantOwnedWidget::query()->create([
        'id' => fake()->uuid(),
        'name' => 'Generator yard',
    ]);

    expect($widget->tenant_id)->toBe($tenant->id);
});

it('scopes tenant-owned queries to the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    DB::table('tenant_owned_widgets')->insert([
        ['id' => fake()->uuid(), 'tenant_id' => $tenant->id, 'name' => 'Visible'],
        ['id' => fake()->uuid(), 'tenant_id' => $otherTenant->id, 'name' => 'Hidden'],
    ]);

    resolve(TenantContext::class)->set($tenant);

    expect(TenantOwnedWidget::query()->pluck('name')->all())->toBe(['Visible']);
});

it('fails tenant-owned queries without tenant context', function (): void {
    resolve(TenantContext::class)->forget();

    TenantOwnedWidget::query()->count();
})->throws(MissingTenantContextException::class);

it('prevents spoofing tenant id during creation', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    resolve(TenantContext::class)->set($tenant);

    TenantOwnedWidget::query()->create([
        'id' => fake()->uuid(),
        'tenant_id' => $otherTenant->id,
        'name' => 'Spoofed',
    ]);
})->throws(TenantMismatchException::class);

it('prevents changing tenant id after creation', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    resolve(TenantContext::class)->set($tenant);

    $widget = TenantOwnedWidget::query()->create([
        'id' => fake()->uuid(),
        'name' => 'Batching plant',
    ]);

    $widget->tenant_id = $otherTenant->id;
    $widget->save();
})->throws(TenantMismatchException::class);
