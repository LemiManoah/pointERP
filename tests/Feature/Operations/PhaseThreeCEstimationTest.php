<?php

declare(strict_types=1);

use App\Actions\Operations\Estimates\ApproveProjectEstimate;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Enums\ProjectEstimateStatus;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProjectPerformanceSummary;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
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
    $this->actingAs($manager)->post(route('project-estimates.approve', $draft))->assertRedirect(route('projects.boq.show', $project));

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

it('saves BOQ hierarchy and approves an unpriced quantity baseline', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail();
    $key = fake()->uuid();

    $this->actingAs($manager)->post(route('project-estimates.store', $project), [
        'title' => 'Unpriced BOQ',
        'currency_code' => 'UGX',
        'lines' => [[
            'work_item_key' => $key,
            'bill' => '2 Building civil works',
            'section' => 'Ground floor',
            'element' => 'Substructures',
            'item_type' => 'measured',
            'boq_reference' => 'B',
            'name' => 'Mass excavation',
            'description' => 'Excavation not exceeding 1.5 metres deep.',
            'unit_of_measure_id' => $unit->id,
            'planned_quantity' => '1648',
            'selling_rate' => '',
            'estimated_unit_cost' => '10000',
            'source_document' => 'Unpriced BOQs.xlsx',
            'source_sheet' => 'Bill No. 2.1 GF',
            'source_row' => 10,
        ]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $draft = ProjectEstimate::query()->where('title', 'Unpriced BOQ')->firstOrFail();
    $line = $draft->lines->sole();
    expect($line->selling_rate)->toBeNull()
        ->and($line->bill)->toBe('2 Building civil works')
        ->and($line->source_row)->toBe(10)
        ->and($draft->pricingSummary()['status'])->toBe('unpriced');

    $this->post(route('project-estimates.approve', $draft))->assertSessionHasNoErrors()->assertRedirect();
    $activity = ProjectActivity::query()->where('estimate_work_item_key', $key)->sole();
    expect($activity->planned_quantity)->toBe('1648.0000')->and($activity->rate_amount)->toBeNull();

    $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page): Assert => $page
        ->where('performance.pricing.status', 'unpriced')
        ->where('performance.totals.baseline_revenue', null)
        ->where('performance.totals.earned_output', null)
        ->where('performance.work_items.0.remaining_quantity', '1648.0000'));
    $this->get(route('project-estimates.create', $project))->assertInertia(fn (Assert $page): Assert => $page
        ->where('source.lines.0.work_item_key', $key)
        ->where('source.lines.0.section', 'Ground floor')
        ->where('source.lines.0.source_sheet', 'Bill No. 2.1 GF')
        ->where('source.lines.0.source_row', 10));
});

it('distinguishes missing rates from zero and keeps allowances out of quantity activities', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail();
    $data = [
        'title' => 'Mixed pricing BOQ', 'currency_code' => 'UGX',
        'lines' => collect(['measured', 'lump_sum', 'provisional_sum'])->map(fn (string $type): array => [
            'work_item_key' => fake()->uuid(), 'item_type' => $type, 'name' => $type,
            'unit_of_measure_id' => $unit->id, 'planned_quantity' => '1',
            'selling_rate' => $type === 'provisional_sum' ? null : '0',
        ])->all(),
    ];
    $draft = resolve(SaveProjectEstimate::class)->handle($project, $data, $manager);
    expect($draft->pricingSummary())->toBe(['status' => 'partially_priced', 'priced_items' => 2, 'total_items' => 3]);
    resolve(ApproveProjectEstimate::class)->handle($draft, $manager);
    expect(ProjectActivity::query()->whereIn('estimate_work_item_key', $draft->lines->pluck('work_item_key'))->count())->toBe(1);
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($project, true);
    expect($summary['totals']['baseline_revenue'])->toBeNull()
        ->and($summary['work_items'][0]['baseline_revenue'])->toBe('0.0000');

    $data['lines'][2]['selling_rate'] = '500';
    $revision = resolve(SaveProjectEstimate::class)->handle($project, $data, $manager);
    resolve(ApproveProjectEstimate::class)->handle($revision, $manager);
    expect($revision->pricingSummary()['status'])->toBe('fully_priced')
        ->and(resolve(ProjectPerformanceSummary::class)->forProject($project, true)['totals']['baseline_revenue'])->toBe('500.0000');
});

it('preserves activity identity and approved progress when pricing a revised baseline', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $baseline = ProjectEstimate::query()->where('project_id', $project->id)->where('is_baseline', true)->firstOrFail();
    $line = $baseline->lines->first();
    $activity = ProjectActivity::query()->where('project_id', $project->id)->where('estimate_work_item_key', $line->work_item_key)->firstOrFail();
    $activity->update(['approved_quantity' => '12']);

    $data = ['title' => 'Priced revision', 'currency_code' => 'UGX', 'lines' => [[
        'work_item_key' => $line->work_item_key, 'name' => $line->name,
        'unit_of_measure_id' => $line->unit_of_measure_id, 'planned_quantity' => $line->planned_quantity,
        'selling_rate' => '25000', 'bill' => 'Bill 2', 'section' => 'Ground floor',
    ]]];
    $draft = resolve(SaveProjectEstimate::class)->handle($project, $data, $manager);
    resolve(ApproveProjectEstimate::class)->handle($draft, $manager);
    expect($activity->refresh()->approved_quantity)->toBe('12.0000')
        ->and($activity->estimate_line_id)->toBe($draft->lines->sole()->id)
        ->and($activity->rate_amount)->toBe('25000.0000');

    $data['lines'][0]['item_type'] = 'provisional_sum';
    $data['lines'][0]['planned_quantity'] = '1';
    $invalid = resolve(SaveProjectEstimate::class)->handle($project, $data, $manager);
    expect(fn () => resolve(ApproveProjectEstimate::class)->handle($invalid, $manager))
        ->toThrow(ValidationException::class);
    expect($draft->refresh()->is_baseline)->toBeTrue();
});

it('rejects invalid BOQ types, allowance quantities and incomplete source locations', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $unit = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail();
    $this->actingAs($manager)->post(route('project-estimates.store', $project), [
        'title' => 'Invalid BOQ', 'currency_code' => 'UGX',
        'lines' => [
            ['name' => 'Invalid item', 'item_type' => 'heading', 'unit_of_measure_id' => $unit->id, 'planned_quantity' => '1'],
            ['name' => 'Allowance', 'item_type' => 'lump_sum', 'unit_of_measure_id' => $unit->id, 'planned_quantity' => '2', 'source_row' => 10, 'section' => 'Ground floor'],
        ],
    ])->assertSessionHasErrors(['lines.0.item_type', 'lines.1.planned_quantity', 'lines.1.source_sheet', 'lines.1.bill']);
});

it('does not expose pricing state or lookup costs to quantity-only viewers', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $estimate = ProjectEstimate::query()->where('is_baseline', true)->firstOrFail();
    $this->actingAs($engineer)->get(route('project-estimates.show', $estimate))->assertInertia(fn (Assert $page): Assert => $page
        ->where('estimate.pricing', null)
        ->where('templates', [])
        ->where('items', fn ($items): bool => collect($items)->every(fn ($item): bool => $item['unit_cost'] === null)));
});
