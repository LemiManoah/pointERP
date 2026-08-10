<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController
{
    public function __invoke(Document $document, DocumentVersion $documentVersion): StreamedResponse
    {
        Gate::authorize('download', $document);
        abort_unless($documentVersion->document_id === $document->id, 404);

        return Storage::disk($documentVersion->disk)->download($documentVersion->path, $documentVersion->original_name);
    }
}
