<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController
{
    public function __invoke(Document $document, DocumentVersion $documentVersion, AuditLogger $auditLogger): StreamedResponse
    {
        Gate::authorize('download', $document);
        abort_unless($documentVersion->document_id === $document->id, 404);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $auditLogger->record('operations.document.downloaded', $document, $actor, [], [
            'document_version_id' => $documentVersion->id,
            'version_number' => $documentVersion->version_number,
            'original_name' => $documentVersion->original_name,
        ]);

        return Storage::disk($documentVersion->disk)->download($documentVersion->path, $documentVersion->original_name);
    }
}
