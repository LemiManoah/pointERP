<?php

declare(strict_types=1);

use App\Models\DocumentLink;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    resolve(TenantContext::class)->set($administrator->tenant);
});

it('gives administrators every ERP permission without granting support access', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $allPermissions = Permission::query()->orderBy('name')->pluck('name')->all();
    $administratorPermissions = $administrator->getAllPermissions()->pluck('name')->sort()->values()->all();

    expect($administrator->is_support)->toBeFalse()
        ->and($administratorPermissions)->toBe($allPermissions)
        ->and(Gate::forUser($administrator)->allows('create', Project::class))->toBeTrue();

    $this->actingAs($administrator)
        ->get(route('projects.index'))
        ->assertOk();
});

it('seeds two project demonstrations with activities and linked records', function (): void {
    $projects = Project::query()
        ->withCount(['activities', 'sites'])
        ->orderBy('reference')
        ->get();

    expect($projects)->toHaveCount(2)
        ->and($projects->every(fn (Project $project): bool => $project->activities_count > 0))->toBeTrue()
        ->and($projects->every(fn (Project $project): bool => $project->sites_count > 0))->toBeTrue()
        ->and($projects->every(fn (Project $project): bool => DocumentLink::query()
            ->where('linkable_type', Project::class)
            ->where('linkable_id', $project->id)
            ->exists()))->toBeTrue();
});
