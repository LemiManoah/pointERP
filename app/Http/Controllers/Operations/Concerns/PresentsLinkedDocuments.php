<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations\Concerns;

use App\Models\Branch;
use App\Models\Contract;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

trait PresentsLinkedDocuments
{
    /**
     * @return array<string, mixed>
     */
    private function documentFormOptions(User $user): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);

        return [
            'documentTypes' => DocumentType::query()
                ->availableToTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'requires_expiry_date' => $type->requires_expiry_date,
                    'is_confidential' => $type->is_confidential,
                ]),
            'documentBranches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name]),
            'documentLinkOptions' => [
                'contracts' => $this->documentContractOptions($user, $tenantId),
                'projects' => $this->documentProjectOptions($user, $tenantId),
                'sites' => $this->documentSiteOptions($user, $tenantId),
                'dailySiteReports' => $this->documentDailySiteReportOptions($user, $tenantId),
                'equipment' => $this->documentEquipmentOptions($user, $tenantId),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linkedDocumentsFor(Model $target, User $user): array
    {
        return Document::query()
            ->with(['type', 'branch', 'currentVersion', 'links.linkable'])
            ->whereHas('links', fn (Builder $query) => $query
                ->where('linkable_type', $target::class)
                ->where('linkable_id', $target->getKey()))
            ->latest()
            ->get()
            ->filter(fn (Document $document): bool => Gate::forUser($user)->allows('view', $document))
            ->map(fn (Document $document): array => $this->linkedDocumentRow($document))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function linkedDocumentRow(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'reference' => $document->reference,
            'document_number' => $document->document_number,
            'revision' => $document->revision,
            'discipline' => $document->discipline,
            'issuer' => $document->issuer,
            'type_name' => $document->type?->name,
            'type_code' => $document->type?->code,
            'branch_name' => $document->branch?->name,
            'confidentiality' => $document->confidentiality,
            'status' => $document->status,
            'document_date' => $document->document_date?->toDateString(),
            'received_on' => $document->received_on?->toDateString(),
            'expires_on' => $document->expires_on?->toDateString(),
            'is_expired' => $document->isExpired(),
            'current_version' => $document->currentVersion instanceof DocumentVersion ? [
                'id' => $document->currentVersion->id,
                'version_number' => $document->currentVersion->version_number,
                'original_name' => $document->currentVersion->original_name,
                'size_bytes' => $document->currentVersion->size_bytes,
            ] : null,
            'links' => $document->links
                ->map(fn (DocumentLink $link): array => [
                    'id' => $link->id,
                    'label' => $this->linkedDocumentLabel($link->linkable),
                    'type' => class_basename((string) $link->linkable_type),
                ])
                ->values(),
        ];
    }

    private function linkedDocumentLabel(?Model $target): string
    {
        return match (true) {
            $target instanceof Contract => $target->reference,
            $target instanceof Project => $target->reference,
            $target instanceof Site => $target->name,
            $target instanceof DailySiteReport => $target->reference,
            $target instanceof Equipment => sprintf('%s - %s', $target->asset_code, $target->name),
            default => 'Unknown record',
        };
    }

    /**
     * @return list<array<string, string>>
     */
    private function documentContractOptions(User $user, string $tenantId): array
    {
        return Contract::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('reference')
            ->get()
            ->filter(fn (Contract $contract): bool => Gate::forUser($user)->allows('view', $contract))
            ->map(fn (Contract $contract): array => ['id' => $contract->id, 'name' => sprintf('%s - %s', $contract->reference, $contract->title)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function documentProjectOptions(User $user, string $tenantId): array
    {
        return Project::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('reference')
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))
            ->map(fn (Project $project): array => ['id' => $project->id, 'name' => sprintf('%s - %s', $project->reference, $project->name)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function documentSiteOptions(User $user, string $tenantId): array
    {
        return Site::query()
            ->with('project')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->filter(fn (Site $site): bool => Gate::forUser($user)->allows('view', $site))
            ->map(fn (Site $site): array => ['id' => $site->id, 'name' => sprintf('%s (%s)', $site->name, $site->project?->reference)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function documentDailySiteReportOptions(User $user, string $tenantId): array
    {
        return DailySiteReport::query()
            ->with('site')
            ->where('tenant_id', $tenantId)
            ->latest('report_date')
            ->get()
            ->filter(fn (DailySiteReport $report): bool => Gate::forUser($user)->allows('view', $report))
            ->map(fn (DailySiteReport $report): array => ['id' => $report->id, 'name' => sprintf('%s - %s', $report->reference, $report->site?->name)])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function documentEquipmentOptions(User $user, string $tenantId): array
    {
        return Equipment::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('asset_code')
            ->get()
            ->filter(fn (Equipment $equipment): bool => Gate::forUser($user)->allows('view', $equipment))
            ->map(fn (Equipment $equipment): array => ['id' => $equipment->id, 'name' => sprintf('%s - %s', $equipment->asset_code, $equipment->name)])
            ->values()
            ->all();
    }
}
