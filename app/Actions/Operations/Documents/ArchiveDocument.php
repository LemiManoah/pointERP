<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class ArchiveDocument
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(Document $document, User $actor): Document
    {
        $oldStatus = $document->status;
        $newStatus = $oldStatus === Document::STATUS_ARCHIVED
            ? Document::STATUS_ACTIVE
            : Document::STATUS_ARCHIVED;

        $document->forceFill([
            'status' => $newStatus,
            'updated_by' => $actor->id,
        ])->save();

        $this->auditLogger->record('operations.document.status_changed', $document, $actor, [
            'status' => $oldStatus,
        ], [
            'status' => $newStatus,
        ]);

        return $document;
    }
}
