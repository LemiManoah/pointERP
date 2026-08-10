<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations\Concerns;

use App\Models\Contract;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

trait PresentsLinkedDocuments
{
    /**
     * @return list<array<string, mixed>>
     */
    private function linkedDocumentsFor(Model $target, User $user): array
    {
        return Document::query()
            ->with(['type', 'branch', 'currentVersion', 'links.linkable'])
            ->whereHas('links', fn ($query) => $query
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
            'type_name' => $document->type->name,
            'type_code' => $document->type->code,
            'branch_name' => $document->branch?->name,
            'confidentiality' => $document->confidentiality,
            'status' => $document->status,
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
            default => 'Unknown record',
        };
    }
}
