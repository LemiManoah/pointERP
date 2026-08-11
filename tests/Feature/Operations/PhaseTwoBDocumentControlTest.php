<?php

declare(strict_types=1);

use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);

    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('lets directors see confidential commercial documents', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $document = Document::query()->where('reference', 'UNRA/WORKS/2021-2022/00369')->firstOrFail();

    $this->actingAs($director)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/documents/index')
            ->has('documents'));

    expect(Gate::forUser($director)->allows('view', $document))->toBeTrue();
});

it('hides commercial documents from site users while showing normal evidence', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $commercialDocument = Document::query()->where('reference', 'UNRA/WORKS/2021-2022/00369')->firstOrFail();
    $normalEvidence = Document::query()->where('reference', 'DSR-BUSUNJU-20241207-PHOTO-001')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/documents/index')
            ->has('documents'));

    expect(Gate::forUser($siteManager)->allows('view', $commercialDocument))->toBeFalse()
        ->and(Gate::forUser($siteManager)->allows('view', $normalEvidence))->toBeTrue();
});

it('shows linked documents on the project page', function (): void {
    $manager = User::query()->where('email', 'pm.gulu@point.test')->firstOrFail();
    $project = Project::query()->where('reference', 'BKH-ROAD')->firstOrFail();

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/projects/show')
            ->where('project.reference', 'BKH-ROAD')
            ->has('documents', 5));
});

it('shows linked documents on site and daily report pages', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $site = Site::query()->where('reference', 'BUSUNJU')->firstOrFail();
    $report = DailySiteReport::query()->where('reference', 'DSR-BUSUNJU-20241207')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/sites/show')
            ->has('documents', 6));

    $this->actingAs($siteManager)
        ->get(route('daily-site-reports.show', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/daily-site-reports/show')
            ->has('documents', 2));
});

it('prevents unauthorized direct downloads of confidential documents', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();
    $document = Document::query()->where('reference', 'UNRA/WORKS/2021-2022/00369')->firstOrFail();
    $version = DocumentVersion::query()->where('document_id', $document->id)->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('documents.versions.download', [$document, $version]))
        ->assertForbidden();
});

it('supersedes older active drawing records with the same document number', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $drawingType = DocumentType::query()->where('code', 'DRAWING')->firstOrFail();
    $branchId = $director->branches()->wherePivot('is_default', true)->value('branches.id')
        ?? $director->branches()->value('branches.id');

    expect($branchId)->not->toBeNull();

    $olderDocument = Document::factory()->create([
        'branch_id' => $branchId,
        'document_type_id' => $drawingType->id,
        'owner_id' => $director->id,
        'document_number' => 'BKH-TEST-DWG-001',
        'revision' => 'A',
        'status' => Document::STATUS_ACTIVE,
    ]);

    $this->actingAs($director)
        ->post(route('documents.store'), [
            'branch_id' => $branchId,
            'document_type_id' => $drawingType->id,
            'title' => 'BKH test drawing revision B',
            'reference' => 'BKH-TEST-DWG-001-REV-B',
            'document_number' => 'BKH-TEST-DWG-001',
            'revision' => 'B',
            'discipline' => 'Roadworks',
            'issuer' => 'Point Design Office',
            'document_date' => now()->toDateString(),
            'received_on' => now()->toDateString(),
            'confidentiality' => Document::CONFIDENTIALITY_NORMAL,
            'status' => Document::STATUS_ACTIVE,
            'file' => UploadedFile::fake()->create('drawing-rev-b.pdf', 100, 'application/pdf'),
            'links' => [],
        ])
        ->assertRedirect();

    expect($olderDocument->refresh()->status)->toBe(Document::STATUS_SUPERSEDED);
});
