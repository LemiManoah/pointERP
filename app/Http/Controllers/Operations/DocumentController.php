<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Documents\ArchiveDocument;
use App\Actions\Operations\Documents\SaveDocument;
use App\Http\Requests\Operations\Documents\StoreDocumentRequest;
use App\Http\Requests\Operations\Documents\UpdateDocumentRequest;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DocumentController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Document::class);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $documents = Document::query()
            ->with(['type', 'branch', 'currentVersion', 'links.linkable'])
            ->where('tenant_id', resolve(TenantContext::class)->id())
            ->latest()
            ->get()
            ->filter(fn (Document $document): bool => Gate::forUser($user)->allows('view', $document))
            ->values();

        return Inertia::render('operations/documents/index', [
            'documents' => $documents->map(fn (Document $document): array => $this->documentRow($document)),
            ...$this->formOptions($user),
        ]);
    }

    public function show(Document $document): Response
    {
        Gate::authorize('view', $document);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $document->load(['type', 'branch', 'currentVersion', 'versions.uploadedBy', 'links.linkable']);

        return Inertia::render('operations/documents/show', [
            'document' => [
                ...$this->documentRow($document),
                'branch_id' => $document->branch_id,
                'document_type_id' => $document->document_type_id,
                'description' => $document->description,
                'document_date' => $document->document_date?->toDateString(),
                'received_on' => $document->received_on?->toDateString(),
                'expires_on' => $document->expires_on?->toDateString(),
                'versions' => $document->versions
                    ->sortByDesc('version_number')
                    ->values()
                    ->map(fn (DocumentVersion $version): array => [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'original_name' => $version->original_name,
                        'mime_type' => $version->mime_type,
                        'size_bytes' => $version->size_bytes,
                        'checksum' => $version->checksum,
                        'notes' => $version->notes,
                        'uploaded_by' => $version->uploadedBy?->name,
                        'uploaded_at' => $version->uploaded_at?->toDateTimeString(),
                    ]),
            ],
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $document),
                'archive' => Gate::forUser($user)->allows('delete', $document),
                'download' => Gate::forUser($user)->allows('download', $document),
                'version' => Gate::forUser($user)->allows('version', $document),
                'link' => Gate::forUser($user)->allows('link', $document),
                'unlink' => Gate::forUser($user)->allows('unlink', $document),
            ],
            ...$this->formOptions($user),
        ]);
    }

    public function store(StoreDocumentRequest $request, SaveDocument $action): RedirectResponse
    {
        Gate::authorize('create', Document::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $document = $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document uploaded.']);

        return to_route('documents.show', $document);
    }

    public function update(UpdateDocumentRequest $request, Document $document, SaveDocument $action): RedirectResponse
    {
        Gate::authorize('update', $document);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $action->handle($data, $actor, $document);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document updated.']);

        return to_route('documents.show', $document);
    }

    public function destroy(Document $document, ArchiveDocument $action): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($document, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document archive status changed.']);

        return to_route('documents.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);

        return [
            'documentTypes' => DocumentType::query()
                ->availableToTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'requires_expiry_date' => $type->requires_expiry_date,
                    'is_confidential' => $type->is_confidential,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name]),
            'linkOptions' => [
                'contracts' => $this->contractOptions($user, $tenantId),
                'projects' => $this->projectOptions($user, $tenantId),
                'sites' => $this->siteOptions($user, $tenantId),
                'dailySiteReports' => $this->dailySiteReportOptions($user, $tenantId),
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function contractOptions(User $user, string $tenantId): array
    {
        return Contract::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('reference')
            ->get()
            ->filter(fn (Contract $contract): bool => Gate::forUser($user)->allows('view', $contract))
            ->map(fn (Contract $contract): array => ['id' => $contract->id, 'name' => sprintf('%s - %s', $contract->reference, $contract->title)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function projectOptions(User $user, string $tenantId): array
    {
        return Project::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('reference')
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))
            ->map(fn (Project $project): array => ['id' => $project->id, 'name' => sprintf('%s - %s', $project->reference, $project->name)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function siteOptions(User $user, string $tenantId): array
    {
        return Site::query()
            ->with('project')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->filter(fn (Site $site): bool => Gate::forUser($user)->allows('view', $site))
            ->map(fn (Site $site): array => ['id' => $site->id, 'name' => sprintf('%s (%s)', $site->name, $site->project?->reference)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function dailySiteReportOptions(User $user, string $tenantId): array
    {
        return DailySiteReport::query()
            ->with('site')
            ->where('tenant_id', $tenantId)
            ->latest('report_date')
            ->get()
            ->filter(fn (DailySiteReport $report): bool => Gate::forUser($user)->allows('view', $report))
            ->map(fn (DailySiteReport $report): array => ['id' => $report->id, 'name' => sprintf('%s - %s', $report->reference, $report->site?->name)])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRow(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'reference' => $document->reference,
            'document_number' => $document->document_number,
            'revision' => $document->revision,
            'discipline' => $document->discipline,
            'issuer' => $document->issuer,
            'type_name' => $document->type->name,
            'type_code' => $document->type->code,
            'branch_name' => $document->branch?->name,
            'confidentiality' => $document->confidentiality,
            'status' => $document->status,
            'document_date' => $document->document_date?->toDateString(),
            'received_on' => $document->received_on?->toDateString(),
            'expires_on' => $document->expires_on?->toDateString(),
            'is_expired' => $document->isExpired(),
            'current_version' => $document->currentVersion instanceof DocumentVersion ? [
                'id' => $document->currentVersion->id,
                'version_number' => $document->currentVersion->version_number,
                'original_name' => $document->currentVersion->original_name,
                'size_bytes' => $document->currentVersion->size_bytes,
            ] : null,
            'links' => $document->links
                ->map(fn (DocumentLink $link): array => [
                    'id' => $link->id,
                    'label' => $this->linkLabel($link->linkable),
                    'type' => class_basename((string) $link->linkable_type),
                ])
                ->values(),
        ];
    }

    private function linkLabel(?Model $target): string
    {
        return match (true) {
            $target instanceof Contract => $target->reference,
            $target instanceof Project => $target->reference,
            $target instanceof Site => $target->name,
            $target instanceof DailySiteReport => $target->reference,
            default => 'Unknown record',
        };
    }
}
