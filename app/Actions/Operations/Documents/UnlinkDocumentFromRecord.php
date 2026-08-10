<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents;

use App\Models\DocumentLink;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class UnlinkDocumentFromRecord
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(DocumentLink $link, User $actor): void
    {
        $document = $link->document;
        $oldValues = $link->toArray();

        $link->delete();

        $this->auditLogger->record('operations.document.unlinked', $document, $actor, $oldValues, []);
    }
}
