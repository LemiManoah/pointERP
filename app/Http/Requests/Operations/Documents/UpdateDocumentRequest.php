<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Documents;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentDiscipline;
use App\Enums\DocumentRevision;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->metadataRules();
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $documentType = DocumentType::query()->find($this->input('document_type_id'));
                $user = $this->user();

                if ($documentType?->requires_expiry_date === true && ! $this->filled('expires_on')) {
                    $validator->errors()->add('expires_on', 'The expiry date is required for this document type.');
                }

                if (! $this->filled('branch_id') && $user instanceof User && ! $user->can('branches.view-all')) {
                    $validator->errors()->add('branch_id', 'Select a branch for this document.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataRules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $user = $this->user();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user instanceof User ? $user : null);

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('tenant_id', $tenantId)->whereIn('id', $branchIds))],
            'document_type_id' => ['required', 'uuid', Rule::exists((new DocumentType)->getTable(), 'id')->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))],
            'owner_id' => ['nullable', 'uuid', Rule::exists((new User)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'external_url' => [
                'nullable',
                'string',
                'max:2048',
                'url:https',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $host = mb_strtolower((string) parse_url($value, PHP_URL_HOST));

                    if (! in_array($host, ['docs.google.com', 'drive.google.com'], true)) {
                        $fail('Only Google Drive or Google Docs links are allowed.');
                    }
                },
            ],
            'document_number' => ['nullable', 'string', 'max:255'],
            'revision' => ['nullable', Rule::enum(DocumentRevision::class)],
            'discipline' => ['nullable', Rule::enum(DocumentDiscipline::class)],
            'issuer_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where(fn (Builder $query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
            'description' => ['nullable', 'string'],
            'document_date' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'confidentiality' => ['required', Rule::enum(DocumentConfidentiality::class)],
            'status' => ['nullable', Rule::in([
                Document::STATUS_ACTIVE,
                Document::STATUS_SUPERSEDED,
                Document::STATUS_EXPIRED,
                Document::STATUS_ARCHIVED,
            ])],
            'links' => ['array'],
            'links.*.type' => ['required_with:links', Rule::in(['contract', 'project', 'site', 'daily_site_report', 'equipment', 'equipment_maintenance_work_order', 'inventory_item', 'expense'])],
            'links.*.id' => ['required_with:links', 'uuid'],
        ];
    }
}
