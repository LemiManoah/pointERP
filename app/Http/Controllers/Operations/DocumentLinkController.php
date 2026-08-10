<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Documents\LinkDocumentToRecord;
use App\Actions\Operations\Documents\UnlinkDocumentFromRecord;
use App\Http\Requests\Operations\Documents\StoreDocumentLinkRequest;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DocumentLinkController
{
    public function store(StoreDocumentLinkRequest $request, Document $document, LinkDocumentToRecord $action): RedirectResponse
    {
        Gate::authorize('link', $document);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{type: string, id: string} $data */
        $data = $request->validated();
        $action->handle($document, $data['type'], $data['id'], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document linked.']);

        return to_route('documents.show', $document);
    }

    public function destroy(Document $document, DocumentLink $documentLink, UnlinkDocumentFromRecord $action): RedirectResponse
    {
        Gate::authorize('unlink', $document);
        abort_unless($documentLink->document_id === $document->id, 404);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($documentLink, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document link removed.']);

        return to_route('documents.show', $document);
    }
}
