<?php

declare(strict_types=1);

use App\Enums\EstimateResourceType;
use App\Models\Project;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkItemTemplate;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkItemTemplateSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    $this->seed(WorkItemTemplateSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('lists work item templates for permitted users', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('work-item-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/work-item-templates/index')
            ->has('templates.data')
            ->where('can.create', true)
            ->where('can.manage', true));
});

it('denies template management to users without permission', function (): void {
    $unpermittedUser = User::query()->where('email', 'site.juba@point.test')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('is_active', true)->firstOrFail();

    $this->actingAs($unpermittedUser)
        ->post(route('work-item-templates.store'), [
            'category' => 'Testing',
            'name' => 'Unauthorized item',
            'unit_of_measure_id' => $unit->id,
        ])
        ->assertForbidden();
});

it('creates a work item template and calculates unit cost dynamically from resource norms', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('is_active', true)->firstOrFail();

    $response = $this->actingAs($manager)->post(route('work-item-templates.store'), [
        'code' => 'TEST-001',
        'category' => 'Concrete Works',
        'name' => 'Test Concrete Grade 30',
        'unit_of_measure_id' => $unit->id,
        'default_selling_rate' => '500000.0000',
        'specifications' => 'High strength mix',
        'is_active' => true,
        'resources' => [
            [
                'resource_type' => EstimateResourceType::Material->value,
                'name' => 'Cement CEM I 52.5N',
                'quantity_per_work_unit' => '8.000000',
                'unit_cost' => '42000.0000', // 8 * 42,000 = 336,000
                'notes' => '8 bags',
            ],
            [
                'resource_type' => EstimateResourceType::Labour->value,
                'name' => 'Mason Gang',
                'quantity_per_work_unit' => '2.000000',
                'unit_cost' => '15000.0000', // 2 * 15,000 = 30,000
                'notes' => '2 hours',
            ],
        ],
    ]);

    $response->assertRedirect(route('work-item-templates.index'));

    $template = WorkItemTemplate::query()->where('code', 'TEST-001')->firstOrFail();
    expect($template->name)->toBe('Test Concrete Grade 30')
        ->and($template->resources)->toHaveCount(2)
        // 336,000 + 30,000 = 366,000.0000 dynamically calculated!
        ->and((float) $template->default_unit_cost)->toBe(366000.0);
});

it('updates an existing work item template and dynamically updates its unit cost', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $template = WorkItemTemplate::query()->where('code', 'CONC-025')->firstOrFail();

    $response = $this->actingAs($manager)->put(route('work-item-templates.update', $template), [
        'code' => 'CONC-025',
        'category' => 'Concrete Works',
        'name' => 'Grade 25 Reinforced Concrete Updated',
        'unit_of_measure_id' => $template->unit_of_measure_id,
        'default_selling_rate' => '480000.0000',
        'resources' => [
            [
                'resource_type' => EstimateResourceType::Material->value,
                'name' => 'Single Material',
                'quantity_per_work_unit' => '5.000000',
                'unit_cost' => '20000.0000', // 5 * 20,000 = 100,000
            ],
        ],
    ]);

    $response->assertRedirect(route('work-item-templates.index'));
    expect($template->refresh()->name)->toBe('Grade 25 Reinforced Concrete Updated')
        ->and((float) $template->default_unit_cost)->toBe(100000.0);
});

it('soft deletes a work item template without affecting existing baselines', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $template = WorkItemTemplate::query()->firstOrFail();

    $this->actingAs($manager)
        ->delete(route('work-item-templates.destroy', $template))
        ->assertRedirect(route('work-item-templates.index'));

    expect(WorkItemTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(WorkItemTemplate::withTrashed()->whereKey($template->id)->exists())->toBeTrue();
});

it('provides work item templates to the project estimate editor', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('project-estimates.create', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/estimates/editor')
            ->has('templates')
            ->where('templates.0.code', 'CONC-025'));
});
