<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents;

use App\Actions\Operations\Documents\Concerns\ResolvesDocumentLinks;
use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class SaveDocument
{
    use ResolvesDocumentLinks;

    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
        private UploadDocumentVersion $uploadDocumentVersion,
        private LinkDocumentToRecord $linkDocumentToRecord,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?Document $document = null): Document
    {
        return DB::transaction(function () use ($actor, $data, $document): Document {
            $oldValues = $document?->fresh()?->toArray() ?? [];
            $document ??= new Document();

            $document->fill([
                'tenant_id' => $this->tenantContext->id(),
                'branch_id' => $data['branch_id'] ?? null,
                'document_type_id' => $data['document_type_id'],
                'owner_id' => $data['owner_id'] ?? $actor->id,
                'title' => $data['title'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'document_date' => $data['document_date'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'confidentiality' => $data['confidentiality'],
                'status' => $data['status'] ?? Document::STATUS_ACTIVE,
                'created_by' => $document->exists ? $document->created_by : $actor->id,
                'updated_by' => $actor->id,
            ]);
            $document->save();

            foreach ((array) ($data['links'] ?? []) as $link) {
                if (! is_array($link)) {
                    continue;
                }
                if (! is_string($link['type'] ?? null)) {
                    continue;
                }
                if (! is_string($link['id'] ?? null)) {
                    continue;
                }
                if ($link['id'] === '') {
                    continue;
                }
                $this->linkDocumentToRecord->handle($document, $link['type'], $link['id'], $actor);
            }

            if (isset($data['file']) && $document->current_version_id === null) {
                $this->uploadDocumentVersion->handle($document, [
                    'file' => $data['file'],
                    'notes' => $data['version_notes'] ?? null,
                ], $actor);
            }

            $event = $oldValues === []
                ? 'operations.document.created'
                : 'operations.document.updated';

            $this->auditLogger->record($event, $document, $actor, $oldValues, $document->fresh()?->toArray() ?? []);

            return $document->refresh();
        });
    }
}
