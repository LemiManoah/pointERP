<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DocumentTypes\SaveDocumentType;
use App\Http\Requests\Operations\DocumentTypes\StoreDocumentTypeRequest;
use App\Http\Requests\Operations\DocumentTypes\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DocumentTypeController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', DocumentType::class);

        $tenantId = resolve(TenantContext::class)->id();

        return Inertia::render('operations/document-types/index', [
            'documentTypes' => DocumentType::query()
                ->availableToTenant($tenantId)
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'description' => $type->description,
                    'requires_expiry_date' => $type->requires_expiry_date,
                    'is_confidential' => $type->is_confidential,
                    'is_system' => $type->is_system,
                    'is_active' => $type->is_active,
                    'tenant_specific' => $type->tenant_id !== null,
                ]),
        ]);
    }

    public function store(StoreDocumentTypeRequest $request, SaveDocumentType $action): RedirectResponse
    {
        Gate::authorize('create', DocumentType::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document type saved.']);

        return to_route('document-types.index');
    }

    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType, SaveDocumentType $action): RedirectResponse
    {
        Gate::authorize('update', $documentType);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $action->handle($data, $actor, $documentType);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document type updated.']);

        return to_route('document-types.index');
    }

    public function destroy(DocumentType $documentType, SaveDocumentType $action): RedirectResponse
    {
        Gate::authorize('delete', $documentType);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle([
            'tenant_specific' => $documentType->tenant_id !== null,
            'name' => $documentType->name,
            'code' => $documentType->code,
            'description' => $documentType->description,
            'requires_expiry_date' => $documentType->requires_expiry_date,
            'is_confidential' => $documentType->is_confidential,
            'is_active' => ! $documentType->is_active,
        ], $actor, $documentType);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document type status changed.']);

        return to_route('document-types.index');
    }
}
