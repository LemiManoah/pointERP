<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkItemTemplate;
use Database\Seeders\QuarryDemoSeeder;
use Database\Seeders\QuarryWorkItemTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(QuarryDemoSeeder::class);
    $this->seed(QuarryWorkItemTemplateSeeder::class);
});

it('seeds a coherent client-facing quarry scenario without regression aliases', function (): void {
    expect(Project::query()->count())->toBe(1)
        ->and(Project::query()->value('reference'))->toBe('NAK-QRY')
        ->and(Site::query()->pluck('reference')->all())->toContain('QUARRY-PIT', 'CRUSHER-YARD')
        ->and(User::query()->where('email', 'like', '%@point.test')->exists())->toBeFalse()
        ->and(WorkItemTemplate::query()->where('code', 'not like', 'QRY-%')->exists())->toBeFalse();
});

it('presents site actions according to backend permissions', function (): void {
    $site = Site::query()->where('reference', 'QUARRY-PIT')->firstOrFail();
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $siteEngineer = User::query()->where('email', 'luate@gmail.com')->firstOrFail();

    $this->actingAs($administrator)->get(route('sites.show', $site))->assertOk()->assertInertia(
        fn (Assert $page): Assert => $page->where('canUpdateSite', true)->where('canManageSiteUsers', true),
    );

    $this->actingAs($siteEngineer)->get(route('sites.show', $site))->assertOk()->assertInertia(
        fn (Assert $page): Assert => $page->where('canManageSiteUsers', false),
    );
});
