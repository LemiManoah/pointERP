<?php

declare(strict_types=1);

use App\Actions\Operations\Estimates\PreviewBoqImport;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BoqWorkbookReader;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->withoutVite();
    config(['cache.stores.file' => ['driver' => 'array']]);
    Cache::forgetDriver('file');
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    $this->manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    resolve(TenantContext::class)->set($this->manager->tenant);
    $this->project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();
});

function boqImportWorkbook(): array
{
    $cell = fn (string $value): array => ['value' => $value, 'formula' => false, 'error' => false];

    return ['name' => 'Unpriced.xlsx', 'sheets' => [[
        'id' => '1', 'name' => 'Ground floor', 'hidden' => false,
        'rows' => [
            1 => ['C' => $cell('Substructures')],
            2 => ['B' => $cell('B'), 'C' => $cell('Excavation'), 'D' => $cell('CM'), 'E' => $cell('1648'), 'G' => $cell('0')],
            3 => ['C' => $cell('TOTAL TO SUMMARY'), 'G' => $cell('0')],
        ],
    ]]];
}

function boqImportMapping(): array
{
    return ['sheet' => '1', 'start_row' => 1, 'end_row' => 20, 'bill' => 'Bill 2', 'section' => 'Ground floor', 'element' => 'Substructures', 'reference' => 'B', 'description' => 'C', 'unit' => 'D', 'quantity' => 'E', 'rate' => 'F', 'amount' => 'G'];
}

it('previews unpriced work without importing headings or totals and requires explicit ambiguous units', function (): void {
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), [boqImportMapping()], null);
    expect($preview['rows'])->toHaveCount(1)
        ->and($preview['skipped'])->toHaveCount(2)
        ->and($preview['rows'][0]['line']['selling_rate'])->toBeNull()
        ->and($preview['rows'][0]['line']['unit_of_measure_id'])->toBe('')
        ->and($preview['rows'][0]['line']['source_row'])->toBe(2)
        ->and($preview['rows'][0]['line']['description'])->toContain('Substructures');
});

it('saves only a draft, retains existing items and handles repeated submission without duplicate revisions', function (): void {
    $this->actingAs($this->manager);
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $baseline = ProjectEstimate::query()->where('project_id', $this->project->id)->where('is_baseline', true)->firstOrFail();
    $oldCount = $baseline->lines()->count();
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), ['sheets' => [boqImportMapping()]])->assertSessionHasNoErrors()->assertRedirect();
    $state = Cache::store('file')->get($key);
    $line = $state['preview']['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $payload = ['title' => 'Imported draft', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'lines' => [$line]];
    $url = route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]);
    $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $draft = ProjectEstimate::query()->where('title', 'Imported draft')->sole();
    expect($draft->isDraft())->toBeTrue()->and($draft->lines()->count())->toBe($oldCount + 1)
        ->and($baseline->refresh()->is_baseline)->toBeTrue();

    $newToken = Str::uuid()->toString();
    $newKey = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$newToken;
    Cache::store('file')->put($newKey, boqImportWorkbook(), now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $newToken]), ['target_id' => $draft->id, 'sheets' => [boqImportMapping()]])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($newKey);
    expect($state['preview']['rows'][0]['line']['work_item_key'])->toBe($line['work_item_key']);
    $payload['preview_id'] = $state['preview_id'];
    $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $newToken]), $payload)->assertSessionHasNoErrors();
    expect($draft->refresh()->lines()->count())->toBe($oldCount + 1);
});

it('rejects a stale preview and tokens belonging to another actor', function (): void {
    $this->actingAs($this->manager);
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), ['sheets' => [boqImportMapping()]])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    $line = $state['preview']['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $baseline = ProjectEstimate::query()->where('project_id', $this->project->id)->where('is_baseline', true)->firstOrFail();
    $baseline->lines()->first()->update(['selling_rate' => '123']);
    $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]), ['title' => 'Stale import', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'lines' => [$line]])->assertSessionHasErrors('lines');
    $other = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $this->actingAs($other)->get(route('project-estimates.import', ['project' => $this->project, 'import' => $token]))->assertSessionHasErrors('file');
});

it('blocks duplicate scoped references and flags amount mismatches', function (): void {
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['rows'][2]['F'] = ['value' => '25', 'formula' => false, 'error' => false];
    $workbook['sheets'][0]['rows'][4] = $workbook['sheets'][0]['rows'][2];
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [boqImportMapping()], null);
    expect($preview['rows'])->toHaveCount(2)
        ->and($preview['rows'][0]['blocked'])->toBeTrue()
        ->and($preview['rows'][1]['blocked'])->toBeTrue()
        ->and(implode(' ', $preview['rows'][0]['warnings']))->toContain('differs from quantity');
});

it('reads shared strings, inline strings and saved formulas from XLSX without converting blank rates to zero', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'boq');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="BOQ" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Excavation</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="10"><c r="B10" t="inlineStr"><is><t>A</t></is></c><c r="C10" t="s"><v>0</v></c><c r="E10"><v>1648</v></c><c r="F10"/><c r="G10"><f>E10*F10</f><v>0</v></c></row></sheetData></worksheet>');
    $zip->close();
    try {
        $sheets = resolve(BoqWorkbookReader::class)->read($path);
        expect($sheets[0]['rows'][10]['C']['value'])->toBe('Excavation')
            ->and($sheets[0]['rows'][10]['B']['value'])->toBe('A')
            ->and($sheets[0]['rows'][10])->not->toHaveKey('F')
            ->and($sheets[0]['rows'][10]['G']['formula'])->toBeTrue();
    } finally {
        unlink($path);
    }
});

it('forbids quantity-only users from accessing the importer', function (): void {
    $engineer = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $this->actingAs($engineer)->get(route('project-estimates.import', $this->project))->assertForbidden();
});

it('rejects expired import tokens and invalid units before saving', function (): void {
    $this->actingAs($this->manager);
    $token = Str::uuid()->toString();
    $url = route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]);
    $this->post($url, ['sheets' => [boqImportMapping()]])->assertSessionHasErrors('file');
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $this->post($url, ['sheets' => [boqImportMapping()]])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    $line = $state['preview']['rows'][0]['line'];
    $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]), [
        'title' => 'Unresolved units', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'lines' => [$line],
    ])->assertSessionHasErrors('lines.0.unit_of_measure_id');
    expect(ProjectEstimate::query()->where('title', 'Unresolved units')->exists())->toBeFalse();
});

it('rejects workbook XML with external entity declarations', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'boq');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<!DOCTYPE Relationships [<!ENTITY external SYSTEM "file:///not-accessed">]><Relationships/>');
    $zip->close();
    try {
        expect(fn () => resolve(BoqWorkbookReader::class)->read($path))->toThrow(ValidationException::class);
    } finally {
        unlink($path);
    }
});

it('detects specification-only changes during revision matching', function (): void {
    $previewer = resolve(PreviewBoqImport::class);
    $workbook = boqImportWorkbook();
    $initial = $previewer->handle($this->project, $workbook, [boqImportMapping()], null);
    $line = $initial['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project, [
        'title' => 'Specification revision', 'currency_code' => 'UGX', 'lines' => [$line],
    ], $this->manager);
    $workbook['sheets'][0]['rows'][1]['C']['value'] = 'Substructures with revised ground conditions';
    $revised = $previewer->handle($this->project, $workbook, [boqImportMapping()], $draft);
    expect($revised['rows'][0]['change'])->toBe('Changed')
        ->and($revised['rows'][0]['line']['work_item_key'])->toBe($line['work_item_key'])
        ->and(implode(' ', $revised['rows'][0]['warnings']))->toContain('description:');
});

it('preserves draft metadata in the preview and rejects subsequent metadata changes', function (): void {
    $this->actingAs($this->manager);
    $baseline = ProjectEstimate::query()->where('project_id', $this->project->id)->where('is_baseline', true)->firstOrFail();
    $existing = resolve(PreviewBoqImport::class)->existingLines($baseline);
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project, [
        'title' => 'Keep this title', 'notes' => 'Keep these notes', 'currency_code' => 'UGX', 'lines' => $existing,
    ], $this->manager);
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), ['target_id' => $draft->id, 'sheets' => [boqImportMapping()]])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    expect($state['preview']['title'])->toBe('Keep this title')->and($state['preview']['notes'])->toBe('Keep these notes');
    $draft->update(['notes' => 'Changed in another session']);
    $line = $state['preview']['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]), [
        'title' => 'Keep this title', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'lines' => [$line],
    ])->assertSessionHasErrors('lines');
    expect($draft->refresh()->notes)->toBe('Changed in another session');
});

it('distinguishes reused letters with different item descriptions', function (): void {
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['rows'][4] = $workbook['sheets'][0]['rows'][2];
    $workbook['sheets'][0]['rows'][4]['C']['value'] = 'Concrete column bases';
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [boqImportMapping()], null);
    expect($preview['rows'])->toHaveCount(2)
        ->and($preview['rows'][0]['blocked'])->toBeFalse()
        ->and($preview['rows'][1]['blocked'])->toBeFalse()
        ->and($preview['rows'][0]['line']['work_item_key'])->not->toBe($preview['rows'][1]['line']['work_item_key']);
});

it('imports percentage adjustments and dayworks for explicit source mapping', function (): void {
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['rows'][2]['D']['value'] = '%';
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [boqImportMapping()], null);
    expect($preview['rows'][0]['blocked'])->toBeFalse()
        ->and($preview['rows'][0]['line']['item_type'])->toBe('percentage_adjustment')
        ->and($preview['rows'][0]['line']['planned_quantity'])->toBe('1')
        ->and($preview['rows'][0]['line']['selling_rate'])->toBeNull()
        ->and($preview['rows'][0]['line']['percentage_rate'])->toBeNull()
        ->and($preview['rows'][0]['line']['percentage_base_keys'])->toBe([]);
    $workbook['sheets'][0]['rows'][2]['D']['value'] = 'hr';
    $workbook['sheets'][0]['name'] = 'Dayworks';
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [boqImportMapping()], null);
    expect($preview['rows'][0]['blocked'])->toBeFalse()
        ->and($preview['rows'][0]['line']['item_type'])->toBe('daywork')
        ->and($preview['rows'][0]['line']['daywork_resource_type'])->toBeNull()
        ->and($preview['rows'][0]['line']['daywork_workforce_trade_id'])->toBeNull();
});

it('classifies commercial entries without making them importable as measured work', function (string $sheet, string $unit, string $classification, bool $review): void {
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['name'] = $sheet;
    $workbook['sheets'][0]['rows'][2]['D']['value'] = $unit;
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [boqImportMapping()], null);

    expect($preview['rows'][0]['classification'])->toBe($classification)
        ->and($preview['rows'][0]['commercial_review'])->toBe($review)
        ->and($preview['rows'][0]['blocked'])->toBe($review)
        ->and($preview['rows'][0]['line']['planned_quantity'])->toBe($classification === 'Percentage adjustment' ? '1' : '1648')
        ->and($preview['rows'][0]['line']['selling_rate'])->toBeNull();
})->with([
    ['Ground floor', 'CM', 'Measured work', false],
    ['Ground floor', 'ITEM', 'Lump sum', false],
    ['Ground floor', 'PS', 'Provisional sum', false],
    ['Preliminaries', 'month', 'Time-based preliminary', false],
    ['Preliminaries', 'ITEM', 'Fixed preliminary', false],
    ['Preliminaries', 'CM', 'Preliminaries', true],
    ['General', 'percent (%)', 'Percentage adjustment', false],
    ['Dayworks', 'hr', 'Dayworks', false],
]);

it('rejects saving a commercial preview row even when its submitted type is changed', function (): void {
    $this->actingAs($this->manager);
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['name'] = 'Preliminaries';
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, $workbook, now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), ['sheets' => [boqImportMapping()]])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    $line = $state['preview']['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $line['item_type'] = 'measured';
    $before = $this->project->estimates()->count();

    $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]), [
        'title' => 'Commercial import', 'currency_code' => 'UGX',
        'preview_id' => $state['preview_id'], 'lines' => [$line],
    ])->assertSessionHasErrors('lines');

    expect($this->project->estimates()->count())->toBe($before);
});

it('imports a time-based preliminary as a draft and prevents changing it to measured work', function (): void {
    $this->actingAs($this->manager);
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['name'] = 'Preliminaries';
    $workbook['sheets'][0]['rows'][2]['D']['value'] = 'hour';
    $workbook['sheets'][0]['rows'][2]['E']['value'] = '6';
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, $workbook, now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), ['sheets' => [boqImportMapping()]])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    $line = $state['preview']['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('tenant_id', $this->manager->tenant_id)->where('code', 'HOUR')->firstOrFail()->id;
    $payload = ['title' => 'Imported time preliminary', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'lines' => [$line]];
    $url = route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]);
    $tampered = $payload;
    $tampered['lines'][0]['item_type'] = 'measured';
    $this->post($url, $tampered)->assertSessionHasErrors('lines');
    $this->post($url, $payload)->assertSessionHasNoErrors();
    $draft = ProjectEstimate::query()->where('title', 'Imported time preliminary')->sole();
    $saved = $draft->lines()->where('work_item_key', $line['work_item_key'])->firstOrFail();
    expect($draft->isDraft())->toBeTrue()
        ->and($saved->item_type->value)->toBe('preliminary_time')
        ->and($saved->planned_quantity)->toBe('6.0000')
        ->and($saved->selling_rate)->toBeNull();
});

it('retains a confirmed rate when a matching reimport is unpriced', function (): void {
    $line = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), [boqImportMapping()], null)['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $line['selling_rate'] = '25000';
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Confirmed price', 'currency_code' => 'UGX', 'lines' => [$line]], $this->manager);
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), [boqImportMapping()], $draft);
    expect($preview['rows'][0]['line']['selling_rate'])->toBe('25000.0000')
        ->and($preview['rows'][0]['line']['work_item_key'])->toBe($line['work_item_key']);
});

it('separates repeated BOQ references under explicit floor headings', function (): void {
    $cell = fn (string $value): array => ['value' => $value, 'formula' => false, 'error' => false];
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $workbook['sheets'][0]['rows'] = [
        1 => ['C' => $cell('ELEMENT NO. 2')],
        2 => ['C' => $cell('Electrical first fix')],
        3 => ['C' => $cell('GROUND FLOOR')],
        4 => $item,
        5 => ['C' => $cell('FIRST FLOOR')],
        6 => $item,
        7 => ['C' => $cell('ELEMENT NO. 3')],
        8 => ['C' => $cell('External lighting')],
        9 => $item,
    ];
    $mapping = [...boqImportMapping(), 'section' => '', 'end_row' => 9];
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [$mapping], null);
    expect($preview['rows'])->toHaveCount(3)
        ->and($preview['rows'][0]['line']['section'])->toBe('GROUND FLOOR')
        ->and($preview['rows'][1]['line']['section'])->toBe('FIRST FLOOR')
        ->and($preview['rows'][2]['line']['section'])->toBe('')
        ->and(array_column($preview['rows'], 'blocked'))->toBe([false, false, false]);

    $fixed = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [[...$mapping, 'section' => 'Manual section']], null);
    expect($fixed['rows'][0]['line']['section'])->toBe('Manual section')
        ->and($fixed['rows'][1]['blocked'])->toBeTrue();
});

it('retains sheet-wide floor headings across elements', function (): void {
    $cell = fn (string $value): array => ['value' => $value, 'formula' => false, 'error' => false];
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $workbook['sheets'][0]['rows'] = [
        1 => ['C' => $cell('GROUND FLOOR')],
        2 => ['C' => $cell('ELEMENT NO. 1')],
        3 => ['C' => $cell('Substructure')],
        4 => $item,
        5 => ['C' => $cell('ELEMENT NO. 2')],
        6 => ['C' => $cell('Frame')],
        7 => $item,
    ];
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [[...boqImportMapping(), 'section' => '', 'end_row' => 7]], null);
    expect($preview['rows'][0]['line']['section'])->toBe('GROUND FLOOR')
        ->and($preview['rows'][1]['line']['section'])->toBe('GROUND FLOOR')
        ->and($preview['rows'][1]['line']['element'])->toBe('Frame');
});

it('detects dayworks from workbook headings even when the selected range excludes the heading', function (): void {
    $cell = fn (string $value): array => ['value' => $value, 'formula' => false, 'error' => false];
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['name'] = 'Series 8000';
    $workbook['sheets'][0]['rows'][1] = ['B' => $cell('SERIES 8000: DAYWORKS (ALL PROVISIONAL)')];
    $workbook['sheets'][0]['rows'][2]['D']['value'] = 'hour (hr)';
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [[...boqImportMapping(), 'start_row' => 2]], null);
    expect($preview['rows'][0]['blocked'])->toBeTrue()
        ->and($preview['rows'][0]['classification'])->toBe('Dayworks')
        ->and($preview['rows'][0]['commercial_review'])->toBeTrue()
        ->and(implode(' ', $preview['rows'][0]['warnings']))->toContain('approved usage')
        ->not->toContain('Ambiguous or duplicate');
});

it('preserves roadwork parent references and section headings', function (): void {
    $cell = fn (string $value): array => ['value' => $value, 'formula' => false, 'error' => false];
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $item['B'] = $cell('(a)(i)');
    $workbook['sheets'][0]['rows'] = [
        1 => ['B' => $cell('SECTION 2200: PREFABRICATED CULVERTS')],
        2 => ['B' => $cell('22.01'), 'C' => $cell('Excavation:')],
        3 => $item,
    ];
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, [[...boqImportMapping(), 'section' => '']], null);
    expect($preview['rows'][0]['line']['boq_reference'])->toBe('22.01(a)(i)')
        ->and($preview['rows'][0]['line']['section'])->toBe('SECTION 2200: PREFABRICATED CULVERTS')
        ->and($preview['rows'][0]['line']['description'])->toContain('22.01 Excavation:');
});
