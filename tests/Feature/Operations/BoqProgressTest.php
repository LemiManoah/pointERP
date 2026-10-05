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
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
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
    $this->actingAs($engineer)->get(route('projects.boq.show', $this->project))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('operations/projects/boq')
            ->where('can.viewCosts', false)->where('performance.work_items.0.baseline_revenue', null));
    $outsider = User::query()->where('email', 'site.juba@point.test')->firstOrFail();
    $this->actingAs($outsider)->get(route('projects.boq.show', $this->project))->assertForbidden();
});

it('creates an isolated idempotent demo project', function (): void {
    $this->artisan('boq:demo', ['--user' => $this->actor->email, '--branch' => $this->project->branch_id])->assertSuccessful();
    $this->artisan('boq:demo', ['--user' => $this->actor->email, '--branch' => $this->project->branch_id])->assertSuccessful();
    $demo = Project::query()->where('reference', 'BOQ-DEMO-COE')->sole();
    expect(ProjectEstimate::query()->where('project_id', $demo->id)->count())->toBe(3)
        ->and(resolve(ProjectPerformanceSummary::class)->forProject($demo, true)['work_items'][0]['approved_progress'])->toBe('250.0000');
});
