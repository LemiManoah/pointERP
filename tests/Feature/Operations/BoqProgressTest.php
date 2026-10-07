<?php

declare(strict_types=1);

use App\Actions\Operations\Boq\PostReportProgress;
use App\Actions\Operations\DailySiteReports\ApproveDailySiteReport;
use App\Actions\Operations\DailySiteReports\SaveDailySiteReport;
use App\Actions\Operations\Estimates\ApproveProjectEstimate;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Actions\Operations\ProjectActivities\SaveProjectActivity;
use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportLabourLine;
use App\Models\DailySiteReportEquipmentLine;
use App\Models\DailySiteReportMaterialLine;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Models\WorkItemTemplate;
use App\Services\ProjectPerformanceSummary;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
    $this->actor = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $this->project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    $this->unit = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail();
    $this->line = ['work_item_key' => (string) Str::uuid(), 'unit_of_measure_id' => $this->unit->id,
        'boq_reference' => 'B', 'name' => 'Excavation', 'planned_quantity' => '1648', 'selling_rate' => '25000', 'item_type' => 'measured'];
    $this->estimate = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'BoQ execution baseline', 'currency_code' => 'UGX', 'lines' => [$this->line]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($this->estimate, $this->actor);
    $this->activity = ProjectActivity::query()->where('estimate_work_item_key', $this->line['work_item_key'])->firstOrFail();
    Notification::fake();
});

it('values preliminaries in the BOQ without creating measured activities or earned output', function (): void {
    $timeUnit = UnitOfMeasure::query()->where('tenant_id', $this->actor->tenant_id)->where('code', 'HOUR')->firstOrFail();
    $fixed = [...$this->line, 'work_item_key' => (string) Str::uuid(), 'name' => 'Site establishment',
        'item_type' => 'preliminary_fixed', 'planned_quantity' => '1', 'selling_rate' => '5000000'];
    $time = [...$this->line, 'work_item_key' => (string) Str::uuid(), 'name' => 'Site supervision',
        'item_type' => 'preliminary_time', 'unit_of_measure_id' => $timeUnit->id,
        'planned_quantity' => '6', 'selling_rate' => '2000000'];
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Preliminaries baseline', 'currency_code' => 'UGX', 'lines' => [$this->line, $fixed, $time]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($draft, $this->actor);
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true);
    $rows = collect($summary['work_items'])->keyBy('item_type');

    expect($rows['preliminary_fixed']['baseline_revenue'])->toBe('5000000.0000')
        ->and($rows['preliminary_time']['baseline_revenue'])->toBe('12000000.0000')
        ->and($rows['preliminary_fixed']['earned_output'])->toBeNull()
        ->and($rows['preliminary_time']['earned_output'])->toBeNull()
        ->and($summary['totals']['baseline_revenue'])->toBe('58200000.0000')
        ->and($summary['totals']['earned_output'])->toBe('0.0000')
        ->and(ProjectActivity::query()->whereIn('estimate_work_item_key', [$fixed['work_item_key'], $time['work_item_key']])->exists())->toBeFalse();

    $time['selling_rate'] = null;
    $unpriced = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Unpriced preliminaries', 'currency_code' => 'UGX', 'lines' => [$this->line, $time]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($unpriced, $this->actor);
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true);
    expect($summary['totals']['baseline_revenue'])->toBeNull()
        ->and($summary['totals']['earned_output'])->toBe('0.0000');
});

it('calculates percentage additions and deductions from an explicit BOQ base', function (): void {
    $adjustment = [...$this->line,
        'work_item_key' => (string) Str::uuid(), 'boq_reference' => 'OH&P',
        'name' => 'Contractor overhead and profit', 'item_type' => 'percentage_adjustment',
        'planned_quantity' => '1', 'selling_rate' => null, 'estimated_unit_cost' => null,
        'percentage_rate' => '10', 'percentage_base_keys' => [$this->line['work_item_key']], 'resources' => [],
    ];
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Percentage baseline', 'currency_code' => 'UGX', 'lines' => [$this->line, $adjustment]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($draft, $this->actor);
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true);
    $percentage = collect($summary['work_items'])->firstWhere('item_type', 'percentage_adjustment');

    expect($percentage['baseline_revenue'])->toBe('4120000.0000')
        ->and($percentage['earned_output'])->toBeNull()
        ->and($summary['totals']['baseline_revenue'])->toBe('45320000.0000')
        ->and(ProjectActivity::query()->where('estimate_work_item_key', $adjustment['work_item_key'])->exists())->toBeFalse();

    $deduction = [...$adjustment, 'percentage_rate' => '-5'];
    $revisedBase = [...$this->line, 'planned_quantity' => '1000'];
    $revision = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Percentage deduction', 'currency_code' => 'UGX', 'lines' => [$revisedBase, $deduction]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($revision, $this->actor);
    $summary = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true);
    expect(collect($summary['work_items'])->firstWhere('item_type', 'percentage_adjustment')['baseline_revenue'])->toBe('-1250000.0000')
        ->and($summary['totals']['baseline_revenue'])->toBe('23750000.0000');
});

it('values a labour daywork automatically from approved DSR usage', function (): void {
    $timeUnit = UnitOfMeasure::query()->where('tenant_id', $this->actor->tenant_id)->where('code', 'HOUR')->firstOrFail();
    $trade = WorkforceTrade::query()->where('tenant_id', $this->actor->tenant_id)->where('is_active', true)->firstOrFail();
    $site = $this->project->sites()->firstOrFail();
    $daywork = [...$this->line,
        'work_item_key' => (string) Str::uuid(), 'boq_reference' => 'DW-01',
        'name' => 'Additional labour', 'item_type' => 'daywork',
        'unit_of_measure_id' => $timeUnit->id, 'planned_quantity' => '100', 'selling_rate' => '5000',
        'daywork_resource_type' => 'labour', 'daywork_workforce_trade_id' => $trade->id,
        'resources' => [],
    ];
    $revision = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Daywork baseline', 'currency_code' => 'UGX', 'lines' => [$this->line, $daywork]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($revision, $this->actor);

    foreach ([DailySiteReport::STATUS_APPROVED => ['3', '8'], DailySiteReport::STATUS_DRAFT => ['2', '10']] as $status => [$headcount, $hours]) {
        $report = DailySiteReport::query()->create([
            'tenant_id' => $this->project->tenant_id, 'branch_id' => $this->project->branch_id,
            'project_id' => $this->project->id, 'site_id' => $site->id,
            'report_date' => now()->subDay()->toDateString(), 'reference' => 'DSR-DAYWORK-'.mb_strtoupper($status),
            'status' => $status, 'approved_by' => $status === DailySiteReport::STATUS_APPROVED ? $this->actor->id : null,
            'approved_at' => $status === DailySiteReport::STATUS_APPROVED ? now() : null,
            'created_by' => $this->actor->id, 'updated_by' => $this->actor->id,
        ]);
        DailySiteReportLabourLine::query()->create([
            'tenant_id' => $report->tenant_id, 'branch_id' => $report->branch_id,
            'daily_site_report_id' => $report->id, 'labour_source' => 'casual',
            'workforce_trade_id' => $trade->id, 'trade_or_role' => $trade->name,
            'headcount' => $headcount, 'hours' => $hours, 'sort_order' => 0,
        ]);
    }

    $summary = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true);
    $row = collect($summary['work_items'])->firstWhere('item_type', 'daywork');

    expect($row['approved_progress'])->toBe('24.0000')
        ->and($row['earned_output'])->toBe('120000.0000')
        ->and($row['daywork_evidence'])->toHaveCount(1)
        ->and(ProjectActivity::query()->where('estimate_work_item_key', $daywork['work_item_key'])->exists())->toBeFalse();
});

it('values equipment and material dayworks from the same approved DSR', function (): void {
    $timeUnit = UnitOfMeasure::query()->where('tenant_id', $this->actor->tenant_id)->where('code', 'HOUR')->firstOrFail();
    $equipment = Equipment::query()->where('asset_code', 'EQ-RLR-002')->firstOrFail();
    $material = InventoryItem::query()->where('code', 'CEM-42')->firstOrFail();
    $site = $this->project->sites()->firstOrFail();
    $equipmentDaywork = [...$this->line,
        'work_item_key' => (string) Str::uuid(), 'boq_reference' => 'DW-EQ',
        'name' => 'Roller daywork', 'item_type' => 'daywork',
        'unit_of_measure_id' => $timeUnit->id, 'planned_quantity' => '20', 'selling_rate' => '200000',
        'daywork_resource_type' => 'equipment', 'daywork_equipment_category_id' => $equipment->equipment_category_id,
        'resources' => [],
    ];
    $materialDaywork = [...$this->line,
        'work_item_key' => (string) Str::uuid(), 'boq_reference' => 'DW-MAT',
        'name' => 'Cement daywork', 'item_type' => 'daywork',
        'unit_of_measure_id' => $material->stock_unit_id, 'planned_quantity' => '50', 'selling_rate' => '40000',
        'daywork_resource_type' => 'material', 'daywork_inventory_item_id' => $material->id,
        'resources' => [],
    ];
    $revision = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Resource daywork baseline', 'currency_code' => 'UGX', 'lines' => [$this->line, $equipmentDaywork, $materialDaywork]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($revision, $this->actor);

    $report = DailySiteReport::query()->create([
        'tenant_id' => $this->project->tenant_id, 'branch_id' => $this->project->branch_id,
        'project_id' => $this->project->id, 'site_id' => $site->id,
        'report_date' => now()->subDay()->toDateString(), 'reference' => 'DSR-DAYWORK-RESOURCES',
        'status' => DailySiteReport::STATUS_APPROVED, 'approved_by' => $this->actor->id,
        'approved_at' => now(), 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id,
    ]);
    DailySiteReportEquipmentLine::query()->create([
        'tenant_id' => $report->tenant_id, 'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id, 'equipment_id' => $equipment->id,
        'equipment_name' => $equipment->name, 'equipment_identifier' => $equipment->asset_code,
        'status' => 'working', 'working_hours' => '7.5000', 'idle_hours' => '0',
        'fleet_posting_status' => 'unposted', 'sort_order' => 0,
    ]);
    DailySiteReportMaterialLine::query()->create([
        'tenant_id' => $report->tenant_id, 'branch_id' => $report->branch_id,
        'daily_site_report_id' => $report->id, 'inventory_item_id' => $material->id,
        'unit_of_measure_id' => $material->stock_unit_id, 'conversion_multiplier' => '1',
        'stock_unit_quantity' => '12', 'material_source' => 'site_store',
        'material_usage_status' => 'posted', 'material_name' => $material->name,
        'quantity' => '12', 'unit' => $material->stockUnit->symbol ?? $material->stockUnit->name,
        'sort_order' => 0,
    ]);

    $summary = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true);
    $rows = collect($summary['work_items'])->where('item_type', 'daywork')->keyBy('daywork_resource_type');

    expect($rows['equipment']['approved_progress'])->toBe('7.5000')
        ->and($rows['equipment']['earned_output'])->toBe('1500000.0000')
        ->and($rows['equipment']['daywork_evidence'])->toHaveCount(1)
        ->and($rows['material']['approved_progress'])->toBe('12.0000')
        ->and($rows['material']['earned_output'])->toBe('480000.0000')
        ->and($rows['material']['daywork_evidence'])->toHaveCount(1)
        ->and(ProjectActivity::query()->whereIn('estimate_work_item_key', [$equipmentDaywork['work_item_key'], $materialDaywork['work_item_key']])->exists())->toBeFalse();
});

it('rejects missing, removed or percentage calculation bases', function (array $baseKeys): void {
    $key = (string) Str::uuid();
    $adjustment = [...$this->line, 'work_item_key' => $key, 'item_type' => 'percentage_adjustment',
        'planned_quantity' => '1', 'selling_rate' => null, 'estimated_unit_cost' => null,
        'percentage_rate' => '10', 'percentage_base_keys' => $baseKeys === ['self'] ? [$key] : $baseKeys, 'resources' => []];
    expect(fn () => resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Invalid percentage', 'currency_code' => 'UGX', 'lines' => [$adjustment]], $this->actor))
        ->toThrow(ValidationException::class);
})->with([
    'no base' => [[]],
    'removed base' => [['00000000-0000-0000-0000-000000000001']],
    'itself' => [['self']],
]);

it('rejects an invalid preliminary quantity or time unit through the request and action', function (string $type, string $quantity, string $error): void {
    $line = [...$this->line, 'work_item_key' => (string) Str::uuid(), 'item_type' => $type, 'planned_quantity' => $quantity];
    $payload = ['title' => 'Invalid preliminary', 'currency_code' => 'UGX', 'lines' => [$line]];
    $this->actingAs($this->actor)->post(route('project-estimates.store', $this->project), $payload)
        ->assertSessionHasErrors('lines.0.'.$error);
    expect(fn () => resolve(SaveProjectEstimate::class)->handle($this->project, $payload, $this->actor))
        ->toThrow(ValidationException::class);
})->with([
    ['preliminary_fixed', '2', 'planned_quantity'],
    ['preliminary_time', '6', 'unit_of_measure_id'],
    ['preliminary_time', '0', 'planned_quantity'],
]);

it('offers active library activities without exposing template costs on BOQ details', function (): void {
    $this->actor->givePermissionTo('work-item-templates.view');
    $template = WorkItemTemplate::query()->create([
        'tenant_id' => $this->actor->tenant_id,
        'category' => 'Earthworks', 'name' => 'Library excavation',
        'unit_of_measure_id' => $this->unit->id,
        'default_selling_rate' => '999', 'default_unit_cost' => '123', 'is_active' => true,
    ]);
    $inactive = WorkItemTemplate::query()->create([
        'tenant_id' => $this->actor->tenant_id,
        'category' => 'Earthworks', 'name' => 'Inactive excavation',
        'unit_of_measure_id' => $this->unit->id, 'is_active' => false,
    ]);

    $this->actingAs($this->actor)
        ->get(route('projects.boq.item', ['project' => $this->project, 'item' => $this->activity->boq_item_id]))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('can.viewActivityLibrary', true)
        ->where('activityTemplates', function ($templates) use ($template, $inactive): bool {
            $rows = collect($templates);
            $row = $rows->firstWhere('id', $template->id);

            return $row !== null && $row['unit_of_measure_id'] === $this->unit->id
                && ! array_key_exists('default_unit_cost', $row)
                && ! array_key_exists('default_selling_rate', $row)
                && $rows->doesntContain('id', $inactive->id);
        }));
});

it('aggregates distinct measured activities once and excludes supporting and draft quantities', function (): void {
    $second = resolve(SaveProjectActivity::class)->handle(['project_id' => $this->project->id,
        'boq_item_id' => $this->activity->boq_item_id, 'name' => 'North wing', 'unit' => 'wrong unit',
        'rate_amount' => '9999', 'progress_method' => 'measured', 'status' => 'active'], $this->actor);
    $support = resolve(SaveProjectActivity::class)->handle(['project_id' => $this->project->id,
        'boq_item_id' => $this->activity->boq_item_id, 'name' => 'Setting out', 'unit' => 'day',
        'rate_amount' => '9999', 'progress_method' => 'supporting', 'status' => 'active'], $this->actor);
    $report = resolve(SaveDailySiteReport::class)->handle([
        'site_id' => $this->project->sites()->firstOrFail()->id, 'report_date' => '2026-09-10',
        'work_lines' => [
            ['project_activity_id' => $this->activity->id, 'description' => 'South wing evidence', 'quantity' => '100.1250'],
            ['project_activity_id' => $second->id, 'description' => 'North wing evidence', 'quantity' => '149.8750'],
            ['project_activity_id' => $support->id, 'quantity' => '2', 'amount' => '9999'],
        ],
    ], $this->actor);
    expect(BoqProgressEntry::query()->where('boq_item_id', $this->activity->boq_item_id)->count())->toBe(0);
    $report->update(['status' => DailySiteReport::STATUS_SUBMITTED]);
    resolve(ApproveDailySiteReport::class)->handle($report, $this->actor);
    resolve(ApproveDailySiteReport::class)->handle($report, $this->actor);
    resolve(PostReportProgress::class)->handle($report->fresh(), $this->actor);

    $row = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true)['work_items'][0];
    expect($row['approved_progress'])->toBe('250.0000')
        ->and($row['remaining_quantity'])->toBe('1398.0000')
        ->and($row['earned_output'])->toBe('6250000.0000')
        ->and(BoqProgressEntry::query()->where('boq_item_id', $this->activity->boq_item_id)->count())->toBe(2)
        ->and($support->fresh()->approved_quantity)->toBe('0.0000')
        ->and($second->unit)->toBe($this->activity->unit)
        ->and($second->rate_amount)->toBe('25000.0000')
        ->and($report->fresh()->output_value)->toBe('6250000.0000');
});

it('preserves identity and measured progress across a quantity revision', function (): void {
    BoqProgressEntry::query()->create(['tenant_id' => $this->project->tenant_id, 'project_id' => $this->project->id,
        'boq_item_id' => $this->activity->boq_item_id, 'project_activity_id' => $this->activity->id,
        'estimate_line_id' => $this->activity->estimate_line_id, 'source_key' => 'fixture:'.Str::uuid(),
        'quantity' => '250', 'unit' => $this->activity->unit, 'measurement_date' => '2026-09-10', 'description' => 'Approved opening evidence']);
    $revision = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Revised scope', 'currency_code' => 'UGX', 'lines' => [[...$this->line, 'planned_quantity' => '200']]], $this->actor);
    expect($revision->lines->sole()->boq_item_id)->toBe($this->activity->boq_item_id);
    resolve(ApproveProjectEstimate::class)->handle($revision, $this->actor);
    $row = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true)['work_items'][0];
    expect($row['approved_progress'])->toBe('250.0000')->and($row['remaining_quantity'])->toBe('0.0000')
        ->and($row['overrun_quantity'])->toBe('50.0000')->and($row['completion_percent'])->toBe('125.00');
});

it('rejects cross-project BoQ activity links', function (): void {
    $other = Project::query()->whereKeyNot($this->project->id)->firstOrFail();
    expect(fn () => resolve(SaveProjectActivity::class)->handle(['project_id' => $other->id,
        'boq_item_id' => $this->activity->boq_item_id, 'name' => 'Wrong project', 'unit' => 'm3', 'status' => 'active'], $this->actor))
        ->toThrow(ValidationException::class);
});

it('serves the BoQ screen without exposing prices to quantity-only users', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $this->actingAs($engineer)->get(route('projects.boq.show', $this->project))
        ->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'boq']));
    $this->get(route('projects.show', ['project' => $this->project, 'tab' => 'boq']))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('operations/projects/show')
            ->where('activeTab', 'boq')->where('boq.can.viewCosts', false)
            ->where('boq.performance.work_items.0.baseline_revenue', null));
    $this->get(route('projects.boq.item', ['project' => $this->project, 'item' => $this->activity->boq_item_id]))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('operations/projects/boq')
            ->where('itemId', $this->activity->boq_item_id)
            ->where('can.viewCosts', false)->where('performance.work_items.0.baseline_revenue', null));
    $outsider = User::query()->where('email', 'site.juba@point.test')->firstOrFail();
    $this->actingAs($outsider)->get(route('projects.boq.show', $this->project))->assertForbidden();
});

it('rejects BOQ item details outside the project approved schedule', function (): void {
    $this->actingAs($this->actor)
        ->get(route('projects.boq.item', ['project' => $this->project, 'item' => (string) Str::uuid()]))
        ->assertNotFound();
});

it('keeps removed BOQ item history readable and preserves progress when reintroduced', function (): void {
    BoqProgressEntry::query()->create(['tenant_id' => $this->project->tenant_id, 'project_id' => $this->project->id,
        'boq_item_id' => $this->activity->boq_item_id, 'project_activity_id' => $this->activity->id,
        'estimate_line_id' => $this->activity->estimate_line_id, 'source_key' => 'fixture:'.Str::uuid(),
        'quantity' => '250', 'unit' => $this->activity->unit, 'measurement_date' => '2026-09-10', 'description' => 'Approved measurement']);
    $replacement = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Different scope', 'currency_code' => 'UGX', 'lines' => [[...$this->line,
            'work_item_key' => (string) Str::uuid(), 'name' => 'Other excavation']]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($replacement, $this->actor);

    $this->actingAs($this->actor)->get(route('projects.boq.item', ['project' => $this->project, 'item' => $this->activity->boq_item_id]))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('historical', true)->where('can.createActivity', false)
        ->where('performance.work_items.0.approved_progress', '250.0000')
        ->where('archivedItems.0.id', $this->activity->boq_item_id)->has('measurements', 1));
    expect($this->activity->fresh()->status)->toBe('inactive');
    expect(fn () => resolve(SaveProjectActivity::class)->handle(['project_id' => $this->project->id,
        'boq_item_id' => $this->activity->boq_item_id, 'name' => 'Cannot add', 'unit' => 'm3', 'status' => 'active'], $this->actor))
        ->toThrow(ValidationException::class);

    $restored = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Scope restored', 'currency_code' => 'UGX', 'lines' => [[...$this->line, 'planned_quantity' => '1800']]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($restored, $this->actor);
    $row = resolve(ProjectPerformanceSummary::class)->forProject($this->project, true)['work_items'][0];
    expect($row['approved_progress'])->toBe('250.0000')->and($row['remaining_quantity'])->toBe('1550.0000');
    $this->get(route('projects.boq.item', ['project' => $this->project, 'item' => $this->activity->boq_item_id]))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('historical', false));
});

it('rejects a unit change when reported BOQ work returns after removal', function (): void {
    BoqProgressEntry::query()->create(['tenant_id' => $this->project->tenant_id, 'project_id' => $this->project->id,
        'boq_item_id' => $this->activity->boq_item_id, 'project_activity_id' => $this->activity->id,
        'estimate_line_id' => $this->activity->estimate_line_id, 'source_key' => 'fixture:'.Str::uuid(),
        'quantity' => '10', 'unit' => $this->activity->unit, 'measurement_date' => '2026-09-10', 'description' => 'Approved measurement']);
    $replacement = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Other scope', 'currency_code' => 'UGX', 'lines' => [[...$this->line, 'work_item_key' => (string) Str::uuid()]]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($replacement, $this->actor);
    $otherUnit = UnitOfMeasure::query()->where('code', 'M2')->firstOrFail();
    $restored = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Changed unit', 'currency_code' => 'UGX', 'lines' => [[...$this->line, 'unit_of_measure_id' => $otherUnit->id]]], $this->actor);
    expect(fn () => resolve(ApproveProjectEstimate::class)->handle($restored, $this->actor))->toThrow(ValidationException::class);
    expect($replacement->fresh()->is_baseline)->toBeTrue();
});

it('creates an isolated idempotent demo project', function (): void {
    $this->artisan('boq:demo', ['--user' => $this->actor->email, '--branch' => $this->project->branch_id])->assertSuccessful();
    $this->artisan('boq:demo', ['--user' => $this->actor->email, '--branch' => $this->project->branch_id])->assertSuccessful();
    $demo = Project::query()->where('reference', 'BOQ-DEMO-COE')->sole();
    expect(ProjectEstimate::query()->where('project_id', $demo->id)->count())->toBe(3)
        ->and(resolve(ProjectPerformanceSummary::class)->forProject($demo, true)['work_items'][0]['approved_progress'])->toBe('250.0000');
});
