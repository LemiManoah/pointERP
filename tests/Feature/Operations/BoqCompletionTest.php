<?php

declare(strict_types=1);

use App\Actions\Operations\Boq\PostReportProgress;
use App\Actions\Operations\Boq\ReconcileProjectProgress;
use App\Actions\Operations\DailySiteReports\ApproveDailySiteReport;
use App\Actions\Operations\DailySiteReports\ApproveDailySiteReportCorrection;
use App\Actions\Operations\DailySiteReports\CreateDailySiteReportCorrection;
use App\Actions\Operations\DailySiteReports\RejectDailySiteReportCorrection;
use App\Actions\Operations\DailySiteReports\SaveDailySiteReport;
use App\Actions\Operations\Estimates\ApproveProjectEstimate;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Models\BoqImport;
use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Enums\WorkforceTradeCategory;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->withoutVite();
    config(['cache.stores.file' => ['driver' => 'array']]);
    Cache::forgetDriver('file');
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    $this->actor = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    resolve(TenantContext::class)->set($this->actor->tenant);
    $this->project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
    WorkforceTrade::query()->create([
        'tenant_id' => $this->actor->tenant_id,
        'code' => 'BOQ-TEST-TRADE',
        'name' => 'BOQ test trade',
        'category' => WorkforceTradeCategory::Skilled,
        'is_active' => true,
        'created_by' => $this->actor->id,
    ]);
    $this->line = ['work_item_key' => (string) Str::uuid(), 'unit_of_measure_id' => UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id,
        'name' => 'Concrete', 'planned_quantity' => '100', 'selling_rate' => '400000', 'item_type' => 'measured'];
    $this->estimate = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Completion baseline', 'currency_code' => 'UGX', 'lines' => [$this->line]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($this->estimate, $this->actor);
    $this->activity = ProjectActivity::query()->where('estimate_work_item_key', $this->line['work_item_key'])->firstOrFail();
    Notification::fake();
    $this->actingAs($this->actor);
});

function completionReport(object $test): DailySiteReport
{
    $report = resolve(SaveDailySiteReport::class)->handle([
        'site_id' => $test->project->sites()->firstOrFail()->id, 'report_date' => '2026-09-11',
        'work_lines' => [['project_activity_id' => $test->activity->id, 'quantity' => '10', 'description' => 'Concrete output']],
    ], $test->actor);
    $report->update(['status' => DailySiteReport::STATUS_SUBMITTED]);
    return resolve(ApproveDailySiteReport::class)->handle($report, $test->actor);
}

it('posts approved corrections once while preserving the original measurement and reconciling the result', function (): void {
    $report = completionReport($this);
    $line = $report->workLines()->sole();
    $correction = resolve(CreateDailySiteReportCorrection::class)->handle($report, $this->actor, 'Measured quantity was overstated', [
        'work_adjustments' => [['line_id' => $line->id, 'quantity_delta' => '-2']],
    ]);
    expect($this->activity->fresh()->approved_quantity)->toBe('10.0000');
    resolve(ApproveDailySiteReportCorrection::class)->handle($correction, $this->actor);
    resolve(PostReportProgress::class)->handle($report->fresh(), $this->actor);
    resolve(\App\Services\RefreshDailySiteReportCosts::class)->handle($report->fresh());
    expect($report->fresh()->output_value)->toBe('3200000.0000')
        ->and($line->fresh()->quantity)->toBe('10.0000')
        ->and($this->activity->fresh()->approved_quantity)->toBe('8.0000')
        ->and(BoqProgressEntry::query()->where('daily_site_report_work_line_id', $line->id)->count())->toBe(2);
    expect(fn () => resolve(ApproveDailySiteReportCorrection::class)->handle($correction, $this->actor))->toThrow(ValidationException::class);
    $findings = collect(resolve(ReconcileProjectProgress::class)->handle($this->project));
    expect($findings->where('record', $line->id)->all())->toBe([])
        ->and($findings->where('type', 'Correction mismatch')->all())->toBe([]);
});

it('leaves rejected quantity corrections unapplied', function (): void {
    $report = completionReport($this);
    $correction = resolve(CreateDailySiteReportCorrection::class)->handle($report, $this->actor, 'Unverified adjustment', [
        'work_adjustments' => [['line_id' => $report->workLines()->sole()->id, 'quantity_delta' => '5']],
    ]);
    resolve(RejectDailySiteReportCorrection::class)->handle($correction, $this->actor, 'Evidence not accepted');
    expect($this->activity->fresh()->approved_quantity)->toBe('10.0000')
        ->and(BoqProgressEntry::query()->where('source_key', 'like', 'correction:'.$correction->id.':%')->exists())->toBeFalse();
});

it('rejects corrections below zero or pointing outside the report', function (bool $foreign): void {
    $report = completionReport($this);
    $correction = resolve(CreateDailySiteReportCorrection::class)->handle($report, $this->actor, 'Invalid quantity adjustment', [
        'work_adjustments' => [['line_id' => $foreign ? (string) Str::uuid() : $report->workLines()->sole()->id, 'quantity_delta' => '-11']],
    ]);
    expect(fn () => resolve(ApproveDailySiteReportCorrection::class)->handle($correction, $this->actor))->toThrow(ValidationException::class);
    expect($this->activity->fresh()->approved_quantity)->toBe('10.0000')
        ->and($correction->fresh()->status)->toBe('submitted');
})->with([false, true]);

it('accepts a quantity correction through the existing report form', function (): void {
    $report = completionReport($this);
    $this->post(route('daily-site-reports.corrections.store', $report), [
        'reason' => 'Rechecked the measured concrete volume',
        'changes' => ['work_adjustments' => [['line_id' => $report->workLines()->sole()->id, 'quantity_delta' => '-1.5']]],
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($report->corrections()->sole()->new_values['work_adjustments'][0]['quantity_delta'])->toBe('-1.5');
});

it('compounds contingency supervision and tax without depending on row order', function (): void {
    $base = [...$this->line, 'planned_quantity' => '1', 'selling_rate' => '1000'];
    $adjust = fn (string $name, string $rate, array $keys): array => [...$base, 'work_item_key' => (string) Str::uuid(),
        'name' => $name, 'item_type' => 'percentage_adjustment', 'selling_rate' => null,
        'percentage_rate' => $rate, 'percentage_base_keys' => $keys];
    $contingency = $adjust('Contingency', '10', [$base['work_item_key']]);
    $supervision = $adjust('Supervision', '11', [$base['work_item_key'], $contingency['work_item_key']]);
    $tax = $adjust('Tax', '18', [$base['work_item_key'], $contingency['work_item_key'], $supervision['work_item_key']]);
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Compounded charges', 'currency_code' => 'UGX', 'lines' => [$tax, $supervision, $contingency, $base]], $this->actor);
    $lines = $draft->lines;
    expect($lines->firstWhere('name', 'Contingency')->boqAmount($lines))->toBe('100.0000')
        ->and($lines->firstWhere('name', 'Supervision')->boqAmount($lines))->toBe('121.0000')
        ->and($lines->firstWhere('name', 'Tax')->boqAmount($lines))->toBe('219.7800');
    $lines->firstWhere('name', 'Concrete')->forceFill(['selling_rate' => null]);
    expect($lines->firstWhere('name', 'Tax')->boqAmount($lines))->toBeNull();
});

it('rejects circular percentage bases before saving a revision', function (): void {
    $a = (string) Str::uuid();
    $b = (string) Str::uuid();
    $adjust = [...$this->line, 'planned_quantity' => '1', 'selling_rate' => null, 'item_type' => 'percentage_adjustment', 'percentage_rate' => '10'];
    $count = ProjectEstimate::query()->where('project_id', $this->project->id)->count();
    expect(fn () => resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Cycle', 'currency_code' => 'UGX', 'lines' => [
            [...$adjust, 'work_item_key' => $a, 'percentage_base_keys' => [$b]],
            [...$adjust, 'work_item_key' => $b, 'percentage_base_keys' => [$a]],
        ]], $this->actor))->toThrow(ValidationException::class);
    expect(ProjectEstimate::query()->where('project_id', $this->project->id)->count())->toBe($count);
});

it('retains the original workbook and review after cache expiry with protected source downloads', function (): void {
    Storage::fake('local');
    $path = tempnam(sys_get_temp_dir(), 'boq-completion');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="BOQ" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Reference</t></is></c><c r="B1" t="inlineStr"><is><t>Description</t></is></c><c r="C1" t="inlineStr"><is><t>Unit</t></is></c><c r="D1" t="inlineStr"><is><t>Quantity</t></is></c><c r="E1" t="inlineStr"><is><t>Client rate</t></is></c><c r="F1" t="inlineStr"><is><t>Amount</t></is></c></row></sheetData></worksheet>');
    $zip->close();
    try {
        $file = new UploadedFile($path, 'Client.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $response = $this->post(route('project-estimates.import.upload', $this->project), ['file' => $file])->assertSessionHasNoErrors();
        $import = BoqImport::query()->where('project_id', $this->project->id)->sole();
        $response->assertRedirect(route('project-estimates.import.review', ['project' => $this->project, 'import' => $import->id]));
        Storage::disk('local')->assertExists($import->path);
        expect($import->getAttribute('sha256'))->toBe(hash_file('sha256', $path));
        Cache::store('file')->flush();
        $this->get(route('project-estimates.import.review', ['project' => $this->project, 'import' => $import->id]))->assertOk();
        $this->get(route('project-estimates.import.source', ['project' => $this->project, 'boqImport' => $import]))->assertDownload('Client.xlsx');
        $other = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
        $this->actingAs($other)->get(route('project-estimates.import', ['project' => $this->project, 'import' => $import->id]))->assertSessionHasErrors('file');
        $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
        $this->actingAs($engineer)->get(route('project-estimates.import.source', ['project' => $this->project, 'boqImport' => $import]))->assertForbidden();
    } finally {
        unlink($path);
    }
});


it('rejects chargeable usage without an approved daywork item and keeps ordinary usage available', function (): void {
    $trade = \App\Models\WorkforceTrade::query()->where('tenant_id', $this->actor->tenant_id)->where('is_active', true)->firstOrFail();
    $data = ['site_id' => $this->project->sites()->firstOrFail()->id, 'report_date' => '2026-09-12',
        'labour_lines' => [['labour_source' => 'casual', 'workforce_trade_id' => $trade->id, 'trade_or_role' => $trade->name, 'headcount' => 2, 'hours' => '8', 'work_type' => 'daywork']]];
    expect(fn () => resolve(SaveDailySiteReport::class)->handle($data, $this->actor))->toThrow(ValidationException::class);
    $data['labour_lines'][0]['work_type'] = 'ordinary';
    $report = resolve(SaveDailySiteReport::class)->handle($data, $this->actor);
    expect($report->labourLines()->sole()->getAttribute('work_type'))->toBe('ordinary');
});

it('corrects daywork eligibility through approval and rejects a stale treatment request', function (): void {
    $trade = \App\Models\WorkforceTrade::query()->where('tenant_id', $this->actor->tenant_id)->where('is_active', true)->firstOrFail();
    $hour = UnitOfMeasure::query()->where('code', 'HOUR')->where('tenant_id', $this->actor->tenant_id)->firstOrFail();
    $daywork = [...$this->line, 'work_item_key' => (string) Str::uuid(), 'name' => 'Extra labour', 'unit_of_measure_id' => $hour->id,
        'item_type' => 'daywork', 'daywork_resource_type' => 'labour', 'daywork_workforce_trade_id' => $trade->id, 'selling_rate' => '5000'];
    $revision = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Daywork revision', 'currency_code' => 'UGX', 'lines' => [$this->line, $daywork]], $this->actor);
    resolve(ApproveProjectEstimate::class)->handle($revision, $this->actor);
    $report = completionReport($this);
    $usage = $report->labourLines()->create(['tenant_id' => $report->tenant_id, 'branch_id' => $report->branch_id,
        'labour_source' => 'casual', 'workforce_trade_id' => $trade->id, 'trade_or_role' => $trade->name, 'headcount' => 2, 'hours' => '8', 'sort_order' => 0]);
    $payload = ['reason' => 'Approved instruction for additional labour', 'changes' => ['usage_treatments' => [
        ['group' => 'labour', 'line_id' => $usage->id, 'work_type' => 'daywork'],
    ]]];
    $this->post(route('daily-site-reports.corrections.store', $report), $payload)->assertSessionHasNoErrors();
    $correction = $report->corrections()->sole();
    $stale = resolve(CreateDailySiteReportCorrection::class)->handle($report, $this->actor, 'Concurrent request', $correction->new_values);
    resolve(ApproveDailySiteReportCorrection::class)->handle($correction, $this->actor);
    expect($usage->fresh()->getAttribute('work_type'))->toBe('daywork');
    $summary = resolve(\App\Services\ProjectPerformanceSummary::class)->forProject($this->project, true);
    expect(collect($summary['work_items'])->firstWhere('item_type', 'daywork')['approved_progress'])->toBe('16.0000');
    expect(fn () => resolve(ApproveDailySiteReportCorrection::class)->handle($stale, $this->actor))->toThrow(ValidationException::class);
});

it('detects a missing correction posting without changing the source report', function (): void {
    $report = completionReport($this);
    $line = $report->workLines()->sole();
    $correction = resolve(CreateDailySiteReportCorrection::class)->handle($report, $this->actor, 'Reviewed measurement change', [
        'work_adjustments' => [['line_id' => $line->id, 'quantity_delta' => '2']],
    ]);
    resolve(ApproveDailySiteReportCorrection::class)->handle($correction, $this->actor);
    BoqProgressEntry::query()->where('source_key', 'correction:'.$correction->id.':'.$line->id)->delete();
    expect(array_column(resolve(ReconcileProjectProgress::class)->handle($this->project), 'type'))->toContain('Correction mismatch', 'Source quantity mismatch')
        ->and($line->fresh()->quantity)->toBe('10.0000');
});
