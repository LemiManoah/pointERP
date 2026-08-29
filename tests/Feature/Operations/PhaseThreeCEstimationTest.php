<?php

declare(strict_types=1);

use App\Enums\ProjectEstimateStatus;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
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

it('shows the approved estimate baseline and actual performance on the project', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/show')
            ->has('estimates', 1)
            ->where('estimates.0.is_baseline', true)
            ->has('performance.work_items', 4)
            ->has('performance.resources', 1));
});

it('lets assigned site users see baseline quantities without estimate costs', function (): void {
    $siteEngineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $estimate = ProjectEstimate::query()->where('is_baseline', true)->firstOrFail();

    $this->actingAs($siteEngineer)
        ->get(route('project-estimates.show', $estimate))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/estimates/editor')
            ->where('can.viewCosts', false)
            ->where('estimate.lines.0.selling_rate', null)
            ->where('estimate.lines.0.estimated_unit_cost', null));
});

it('approves a new estimate revision into work items without granting site users approval', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $siteEngineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail();
    $workItemKey = fake()->uuid();

    $this->actingAs($manager)->post(route('project-estimates.store', $project), [
        'title' => 'BKH Road revised execution estimate',
        'currency_code' => 'UGX',
        'lines' => [[
            'work_item_key' => $workItemKey,
            'unit_of_measure_id' => $unit->id,
            'boq_reference' => 'TEST-01',
            'code' => 'TEST-WORK',
            'name' => 'Test drainage work',
            'planned_quantity' => '1000',
            'selling_rate' => '50000',
            'estimated_unit_cost' => '32000',
            'resources' => [],
        ]],
    ])->assertRedirect();

    $draft = ProjectEstimate::query()->where('project_id', $project->id)->where('status', ProjectEstimateStatus::Draft)->firstOrFail();
    $this->actingAs($siteEngineer)->post(route('project-estimates.approve', $draft))->assertForbidden();
    $this->actingAs($manager)->post(route('project-estimates.approve', $draft))->assertRedirect(route('projects.show', $project));

    expect($draft->refresh()->status)->toBe(ProjectEstimateStatus::Approved)
        ->and($draft->is_baseline)->toBeTrue()
        ->and(ProjectEstimate::query()->where('project_id', $project->id)->where('status', ProjectEstimateStatus::Superseded)->count())->toBe(1)
        ->and(ProjectActivity::query()->where('project_id', $project->id)->where('estimate_work_item_key', $workItemKey)->value('approved_quantity'))->toBe('0.0000');
});

it('keeps users outside the project from viewing its estimate', function (): void {
    $jubaManager = User::query()->where('email', 'site.juba@point.test')->firstOrFail();
    $estimate = ProjectEstimate::query()->where('is_baseline', true)->firstOrFail();

    $this->actingAs($jubaManager)->get(route('project-estimates.show', $estimate))->assertForbidden();
});
