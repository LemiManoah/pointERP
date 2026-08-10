<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Documents\UploadDocumentVersion;
use App\Http\Requests\Operations\Documents\StoreDocumentVersionRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DocumentVersionController
{
    public function store(StoreDocumentVersionRequest $request, Document $document, UploadDocumentVersion $action): RedirectResponse
    {
        Gate::authorize('version', $document);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{file: UploadedFile, notes?: string|null} $data */
        $data = $request->validated();
        $action->handle($document, $data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'New document version uploaded.']);

        return to_route('documents.show', $document);
    }
}
