<?php

declare(strict_types=1);

namespace App\Actions\Operations\DocumentTypes;

use App\Models\DocumentType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;

final readonly class SaveDocumentType
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?DocumentType $documentType = null): DocumentType
    {
        $oldValues = $documentType?->fresh()?->toArray() ?? [];
        $documentType ??= new DocumentType();

        $documentType->fill([
            'tenant_id' => $data['tenant_specific'] ?? false ? $this->tenantContext->id() : null,
            'name' => $data['name'],
            'code' => mb_strtoupper((string) $data['code']),
            'description' => $data['description'] ?? null,
            'requires_expiry_date' => (bool) ($data['requires_expiry_date'] ?? false),
            'is_confidential' => (bool) ($data['is_confidential'] ?? false),
            'is_system' => $documentType->exists ? $documentType->is_system : false,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $documentType->exists ? $documentType->created_by : $actor->id,
            'updated_by' => $actor->id,
        ]);
        $documentType->save();

        $event = $oldValues === []
            ? 'operations.document_type.created'
            : 'operations.document_type.updated';

        $this->auditLogger->record($event, $documentType, $actor, $oldValues, $documentType->fresh()?->toArray() ?? []);

        return $documentType;
    }
}
