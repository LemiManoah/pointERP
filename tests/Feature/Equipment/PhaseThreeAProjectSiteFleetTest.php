<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\EquipmentScopeSummary;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows current fleet deployment and DSR reconciliation on a project', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/show')
            ->where('canViewFleet', true)
            ->has('fleet.summary')
            ->has('fleet.equipment')
            ->has('fleet.reconciliation'));

    $fleet = resolve(EquipmentScopeSummary::class)->forProject($project, $manager);
    $visibleIds = collect($fleet['equipment'])->pluck('id');

    expect($visibleIds)->not->toBeEmpty()
        ->and(Equipment::query()->whereIn('id', $visibleIds)->where('current_project_id', '!=', $project->id)->exists())->toBeFalse();
});

it('limits the site fleet panel to the selected site', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'BUSUNJU')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/sites/show')
            ->where('canViewFleet', true)
            ->has('fleet.summary')
            ->has('fleet.equipment')
            ->has('fleet.reconciliation'));

    $fleet = resolve(EquipmentScopeSummary::class)->forSite($site, $siteManager);
    $visibleIds = collect($fleet['equipment'])->pluck('id');

    expect(Equipment::query()->whereIn('id', $visibleIds)->where('current_site_id', '!=', $site->id)->exists())->toBeFalse();
});

it('omits all fleet data when the project user lacks equipment permission', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $role = $siteManager->roles()->where('name', 'Site Manager')->firstOrFail();

    expect($role)->toBeInstanceOf(Role::class);
    $role->revokePermissionTo('equipment.view');
    $siteManager->unsetRelation('roles');

    $this->actingAs($siteManager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('canViewFleet', false)
            ->where('fleet', null));
});
