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
        'id' => '1', 'name' => 'BOQ', 'hidden' => false,
        'rows' => [
            1 => ['A' => $cell('Reference'), 'B' => $cell('Description'), 'C' => $cell('Unit'), 'D' => $cell('Quantity'), 'E' => $cell('Client rate'), 'F' => $cell('Amount')],
            2 => ['A' => $cell('A'), 'B' => $cell('Excavation'), 'C' => $cell('CM'), 'D' => $cell('1648'), 'F' => $cell('0')],
            3 => ['B' => $cell('TOTAL TO SUMMARY'), 'F' => $cell('0')],
        ],
    ]]];
}

it('previews unpriced work without importing headings or totals and requires explicit ambiguous units', function (): void {
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), null);
    expect($preview['rows'])->toHaveCount(1)
        ->and($preview['skipped'])->toHaveCount(1)
        ->and($preview['rows'][0]['line']['selling_rate'])->toBeNull()
        ->and($preview['rows'][0]['line']['unit_of_measure_id'])->toBe('')
        ->and($preview['rows'][0]['line']['source_row'])->toBe(2)
        ->and($preview['rows'][0]['line']['description'])->toContain('Excavation');
});

it('saves only a draft, retains existing items and handles repeated submission without duplicate revisions', function (): void {
    $this->actingAs($this->manager);
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $baseline = ProjectEstimate::query()->where('project_id', $this->project->id)->where('is_baseline', true)->firstOrFail();
    $oldCount = $baseline->lines()->count();
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), [])->assertSessionHasNoErrors()->assertRedirect();
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
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $newToken]), ['target_id' => $draft->id])->assertSessionHasNoErrors();
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
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), [])->assertSessionHasNoErrors();
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
    $workbook['sheets'][0]['rows'][2]['E'] = ['value' => '25', 'formula' => false, 'error' => false];
    $workbook['sheets'][0]['rows'][4] = $workbook['sheets'][0]['rows'][2];
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
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
    $this->post($url, [])->assertSessionHasErrors('file');
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $this->post($url, [])->assertSessionHasNoErrors();
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
    $initial = $previewer->handle($this->project, $workbook, null);
    $line = $initial['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project, [
        'title' => 'Specification revision', 'currency_code' => 'UGX', 'lines' => [$line],
    ], $this->manager);
    $workbook['sheets'][0]['rows'][3] = $workbook['sheets'][0]['rows'][2];
    $workbook['sheets'][0]['rows'][2] = ['B' => ['value' => 'Substructures', 'formula' => false, 'error' => false]];
    $workbook['sheets'][0]['rows'][2]['B']['value'] = 'Substructures with revised ground conditions';
    $revised = $previewer->handle($this->project, $workbook, $draft);
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
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), ['target_id' => $draft->id])->assertSessionHasNoErrors();
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
    $workbook['sheets'][0]['rows'][4]['B']['value'] = 'Concrete column bases';
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'])->toHaveCount(2)
        ->and($preview['rows'][0]['blocked'])->toBeFalse()
        ->and($preview['rows'][1]['blocked'])->toBeFalse()
        ->and($preview['rows'][0]['line']['work_item_key'])->not->toBe($preview['rows'][1]['line']['work_item_key']);
});

it('recognizes percentage and daywork rows in the fixed BOQ sheet', function (): void {
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['rows'][2]['C']['value'] = '%';
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'][0]['blocked'])->toBeFalse()
        ->and($preview['rows'][0]['line']['item_type'])->toBe('percentage_adjustment')
        ->and($preview['rows'][0]['line']['planned_quantity'])->toBe('1')
        ->and($preview['rows'][0]['line']['selling_rate'])->toBeNull();

    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $item['B']['value'] = 'Unskilled labour';
    $item['C']['value'] = 'hr';
    $workbook['sheets'][0]['rows'][2] = ['B' => ['value' => 'DAYWORKS', 'formula' => false, 'error' => false]];
    $workbook['sheets'][0]['rows'][3] = $item;
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'][0]['blocked'])->toBeTrue()
        ->and($preview['rows'][0]['daywork_resource_required'])->toBeTrue()
        ->and($preview['rows'][0]['line']['item_type'])->toBe('daywork')
        ->and($preview['rows'][0]['line']['daywork_resource_type'])->toBeNull();
});

it('classifies commercial entries from headings and units in the fixed BOQ sheet', function (string $heading, string $unit, string $classification, bool $review): void {
    $workbook = boqImportWorkbook();
    $row = 2;
    if ($heading !== '') {
        $item = $workbook['sheets'][0]['rows'][2];
        $workbook['sheets'][0]['rows'][2] = ['B' => ['value' => $heading, 'formula' => false, 'error' => false]];
        $workbook['sheets'][0]['rows'][3] = $item;
        $row = 3;
    }

    $workbook['sheets'][0]['rows'][$row]['C']['value'] = $unit;
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);

    expect($preview['rows'][0]['classification'])->toBe($classification)
        ->and($preview['rows'][0]['commercial_review'])->toBe($review)
        ->and($preview['rows'][0]['blocked'])->toBe($review || $classification === 'Dayworks')
        ->and($preview['rows'][0]['line']['planned_quantity'])->toBe($classification === 'Percentage adjustment' ? '1' : '1648')
        ->and($preview['rows'][0]['line']['selling_rate'])->toBeNull();
})->with([
    ['', 'CM', 'Measured work', false],
    ['', 'ITEM', 'Lump sum', false],
    ['', 'PS', 'Provisional sum', false],
    ['PRELIMINARIES', 'month', 'Time-based preliminary', false],
    ['PRELIMINARIES', 'ITEM', 'Fixed preliminary', false],
    ['PRELIMINARIES', 'CM', 'Preliminaries', true],
    ['', 'percent (%)', 'Percentage adjustment', false],
    ['DAYWORKS', 'hr', 'Dayworks', false],
]);

it('rejects saving a commercial preview row even when its submitted type is changed', function (): void {
    $this->actingAs($this->manager);
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $workbook['sheets'][0]['rows'][2] = ['B' => ['value' => 'PRELIMINARIES', 'formula' => false, 'error' => false]];
    $workbook['sheets'][0]['rows'][3] = $item;
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, $workbook, now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), [])->assertSessionHasNoErrors();
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
    $item = $workbook['sheets'][0]['rows'][2];
    $item['C']['value'] = 'hour';
    $item['D']['value'] = '6';
    $workbook['sheets'][0]['rows'][2] = ['B' => ['value' => 'PRELIMINARIES', 'formula' => false, 'error' => false]];
    $workbook['sheets'][0]['rows'][3] = $item;
    $token = Str::uuid()->toString();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, $workbook, now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), [])->assertSessionHasNoErrors();
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
    $line = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), null)['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $line['selling_rate'] = '25000';
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Confirmed price', 'currency_code' => 'UGX', 'lines' => [$line]], $this->manager);
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), $draft);
    expect($preview['rows'][0]['line']['selling_rate'])->toBe('25000.0000')
        ->and($preview['rows'][0]['line']['work_item_key'])->toBe($line['work_item_key']);
});

it('separates repeated BOQ references under floor headings in the standard sheet', function (): void {
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $workbook['sheets'][0]['rows'] = [
        1 => $workbook['sheets'][0]['rows'][1],
        2 => ['B' => ['value' => 'GROUND FLOOR', 'formula' => false, 'error' => false]],
        3 => $item,
        4 => ['B' => ['value' => 'FIRST FLOOR', 'formula' => false, 'error' => false]],
        5 => $item,
    ];

    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'])->toHaveCount(2)
        ->and($preview['rows'][0]['line']['section'])->toBe('GROUND FLOOR')
        ->and($preview['rows'][1]['line']['section'])->toBe('FIRST FLOOR')
        ->and(array_column($preview['rows'], 'blocked'))->toBe([false, false]);
});

it('keeps floor headings across elements in the standard sheet', function (): void {
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $heading = fn (string $value): array => ['B' => ['value' => $value, 'formula' => false, 'error' => false]];
    $workbook['sheets'][0]['rows'] = [
        1 => $workbook['sheets'][0]['rows'][1],
        2 => $heading('GROUND FLOOR'),
        3 => $heading('ELEMENT NO. 1'),
        4 => $heading('Substructure'),
        5 => $item,
        6 => $heading('ELEMENT NO. 2'),
        7 => $heading('Frame'),
        8 => $item,
    ];

    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'][0]['line']['section'])->toBe('GROUND FLOOR')
        ->and($preview['rows'][1]['line']['section'])->toBe('GROUND FLOOR')
        ->and($preview['rows'][1]['line']['element'])->toBe('Frame');
});

it('detects daywork rows from a heading in the standard sheet', function (): void {
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $item['B']['value'] = 'Unskilled labour';
    $item['C']['value'] = 'hour (hr)';
    $workbook['sheets'][0]['rows'][2] = ['B' => ['value' => 'SERIES 8000: DAYWORKS (ALL PROVISIONAL)', 'formula' => false, 'error' => false]];
    $workbook['sheets'][0]['rows'][3] = $item;

    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'][0]['blocked'])->toBeTrue()
        ->and($preview['rows'][0]['classification'])->toBe('Dayworks')
        ->and($preview['rows'][0]['commercial_review'])->toBeFalse()
        ->and($preview['rows'][0]['daywork_resource_required'])->toBeTrue();
});

it('preserves parent references and section headings in the standard sheet', function (): void {
    $workbook = boqImportWorkbook();
    $item = $workbook['sheets'][0]['rows'][2];
    $item['A']['value'] = '(a)(i)';
    $item['B']['value'] = 'Excavation in soil';
    $workbook['sheets'][0]['rows'] = [
        1 => $workbook['sheets'][0]['rows'][1],
        2 => ['B' => ['value' => 'SECTION 2200: PREFABRICATED CULVERTS', 'formula' => false, 'error' => false]],
        3 => ['A' => ['value' => '22.01', 'formula' => false, 'error' => false], 'B' => ['value' => 'Excavation:', 'formula' => false, 'error' => false]],
        4 => $item,
    ];

    $preview = resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null);
    expect($preview['rows'][0]['line']['boq_reference'])->toBe('22.01(a)(i)')
        ->and($preview['rows'][0]['line']['section'])->toBe('SECTION 2200: PREFABRICATED CULVERTS')
        ->and($preview['rows'][0]['line']['description'])->toContain('22.01 Excavation:');
});

it('rejects workbooks that do not use the standard BOQ template', function (): void {
    $workbook = boqImportWorkbook();
    $workbook['sheets'][0]['rows'][1]['B']['value'] = 'Item details';
    expect(fn () => resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null))
        ->toThrow(ValidationException::class);

    $workbook = boqImportWorkbook();
    $workbook['sheets'][] = ['id' => '2', 'name' => 'Other BOQ', 'hidden' => false, 'rows' => []];
    expect(fn () => resolve(PreviewBoqImport::class)->handle($this->project, $workbook, null))
        ->toThrow(ValidationException::class);
});

it('updates only matching prices and preserves scope resources and blank existing prices', function (): void {
    $this->actingAs($this->manager);
    $preview = resolve(PreviewBoqImport::class)->handle($this->project, boqImportWorkbook(), null);
    $line = $preview['rows'][0]['line'];
    $line['unit_of_measure_id'] = UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id;
    $line['selling_rate'] = '25';
    $draft = resolve(SaveProjectEstimate::class)->handle($this->project,
        ['title' => 'Price update target', 'currency_code' => 'UGX', 'lines' => [$line]], $this->manager);
    foreach (['30', null] as $price) {
        $token = (string) Str::uuid();
        $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
        Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
        $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]),
            ['target_id' => $draft->id])->assertSessionHasNoErrors();
        $state = Cache::store('file')->get($key);
        $proposed = [...$state['preview']['rows'][0]['line'], 'unit_of_measure_id' => $line['unit_of_measure_id'],
            'planned_quantity' => '999', 'name' => 'Attempted scope change', 'selling_rate' => $price];
        $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]), [
            'title' => $draft->title, 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'],
            'import_mode' => 'prices', 'lines' => [$proposed],
        ])->assertSessionHasNoErrors();
        $saved = $draft->fresh()->lines()->sole();
        expect($saved->name)->toBe($line['name'])->and($saved->planned_quantity)->toBe('1648.0000')
            ->and($saved->selling_rate)->toBe('30.0000')->and($saved->work_item_key)->toBe($line['work_item_key']);
    }
});

it('omits only explicitly reviewed missing items and keeps the approved revision unchanged', function (): void {
    $this->actingAs($this->manager);
    $baseline = ProjectEstimate::query()->where('project_id', $this->project->id)->where('is_baseline', true)->firstOrFail();
    $oldCount = $baseline->lines()->count();
    $token = (string) Str::uuid();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), [])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    $line = [...$state['preview']['rows'][0]['line'], 'unit_of_measure_id' => UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id];
    $payload = ['title' => 'Reviewed omissions', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'lines' => [$line], 'remove_keys' => [(string) Str::uuid()]];
    $url = route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]);
    $this->post($url, $payload)->assertSessionHasErrors('remove_keys');
    $payload['remove_keys'] = array_column($state['preview']['retained'], 'work_item_key');
    $this->post($url, $payload)->assertSessionHasNoErrors();
    expect(ProjectEstimate::query()->where('title', 'Reviewed omissions')->sole()->lines()->count())->toBe(1)
        ->and($baseline->fresh()->lines()->count())->toBe($oldCount);
});

it('refuses a new item in a prices-only import', function (): void {
    $this->actingAs($this->manager);
    $token = (string) Str::uuid();
    $key = 'boq-import:'.$this->project->tenant_id.':'.$this->manager->id.':'.$this->project->id.':'.$token;
    Cache::store('file')->put($key, boqImportWorkbook(), now()->addHours(2));
    $this->post(route('project-estimates.import.preview', ['project' => $this->project, 'import' => $token]), [])->assertSessionHasNoErrors();
    $state = Cache::store('file')->get($key);
    $line = [...$state['preview']['rows'][0]['line'], 'unit_of_measure_id' => UnitOfMeasure::query()->where('code', 'M3')->firstOrFail()->id];
    $this->post(route('project-estimates.import.store', ['project' => $this->project, 'import' => $token]), [
        'title' => 'Prices only', 'currency_code' => 'UGX', 'preview_id' => $state['preview_id'], 'import_mode' => 'prices', 'lines' => [$line],
    ])->assertSessionHasErrors('lines');
});
