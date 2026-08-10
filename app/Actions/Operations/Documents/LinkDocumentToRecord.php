<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents;

use App\Actions\Operations\Documents\Concerns\ResolvesDocumentLinks;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\User;
use App\Services\AuditLogger;

final readonly class LinkDocumentToRecord
{
    use ResolvesDocumentLinks;

    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    public function handle(Document $document, string $type, string $id, User $actor): DocumentLink
    {
        $target = $this->resolveLinkTarget($type, $id, $actor);

        $link = DocumentLink::query()->firstOrCreate(
            [
                'document_id' => $document->id,
                'linkable_type' => $target::class,
                'linkable_id' => $target->getKey(),
            ],
            [
                'tenant_id' => $document->tenant_id,
                'created_by' => $actor->id,
            ],
        );

        $this->auditLogger->record('operations.document.linked', $document, $actor, [], [
            'linkable_type' => $target::class,
            'linkable_id' => $target->getKey(),
        ]);

        return $link;
    }
}
