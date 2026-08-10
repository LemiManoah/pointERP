<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
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

it('lets a project manager see the seeded road project with commercial rates', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/show')
            ->where('project.reference', 'BKH-ROAD')
            ->where('canViewRates', true)
            ->where('activities.0.rate_amount', '4000000.0000'));
});

it('hides activity rates from site managers', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/show')
            ->where('canViewRates', false)
            ->where('activities.0.rate_amount', null));
});

it('includes site-assigned users in the project list', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/index')
            ->has('projects', 1)
            ->where('projects.0.reference', 'BKH-ROAD'));
});

it('forbids operational users without project permission', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    $this->actingAs($storeKeeper)
        ->get(route('projects.index'))
        ->assertForbidden();
});

it('lets project managers assign site users', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $siteUser = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'KIBOGA-HOIMA')->firstOrFail();

    $this->actingAs($manager)
        ->post(route('sites.users.store', $site), [
            'users' => [
                [
                    'user_id' => $siteUser->id,
                    'role' => 'site_engineer',
                    'can_submit_dsr' => true,
                    'can_review_dsr' => false,
                ],
            ],
        ])
        ->assertRedirect(route('sites.show', $site));

    expect($site->users()->whereKey($siteUser->id)->exists())->toBeTrue();
});

it('forbids site managers from managing project activities', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $site = Site::query()->where('reference', 'BUSUNJU')->firstOrFail();

    $this->actingAs($siteManager)
        ->post(route('project-activities.store'), [
            'project_id' => $project->id,
            'site_id' => $site->id,
            'code' => 'TEST-ACT',
            'name' => 'Unauthorized BOQ activity',
            'unit' => 'm3',
            'planned_quantity' => '10',
            'approved_quantity' => '0',
            'rate_amount' => '1000',
            'currency_code' => 'UGX',
            'status' => 'active',
            'sort_order' => 99,
        ])
        ->assertForbidden();

    expect(ProjectActivity::query()->where('code', 'TEST-ACT')->exists())->toBeFalse();
});
