<?php

declare(strict_types=1);

namespace App\Actions\Operations\Contracts;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveContract
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {
        //
    }

    /**
     * @param  array{branch_id: string, customer_id: string, reference: string, title: string, scope_summary?: string|null, contract_value?: string|null, currency_code: string, starts_on?: string|null, ends_on?: string|null, retention_percent?: string|null, payment_terms?: string|null, status: string}  $data
     */
    public function handle(array $data, User $actor, ?Contract $contract = null): Contract
    {
        $customer = Customer::query()->whereKey($data['customer_id'])->firstOrFail();

        if ($customer->branch_id !== null && $customer->branch_id !== $data['branch_id']) {
            throw ValidationException::withMessages([
                'customer_id' => 'The selected customer is not available for that branch.',
            ]);
        }

        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $data['branch_id'],
            'customer_id' => $data['customer_id'],
            'reference' => Str::upper($data['reference']),
            'title' => $data['title'],
            'scope_summary' => $data['scope_summary'] ?? null,
            'contract_value' => $data['contract_value'] ?? null,
            'currency_code' => Str::upper($data['currency_code']),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'retention_percent' => $data['retention_percent'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'status' => $data['status'],
            'updated_by' => $actor->id,
        ];

        $oldValues = $contract instanceof Contract ? $contract->only(array_keys($attributes)) : [];

        if ($contract instanceof Contract) {
            $contract->update($attributes);
            $event = 'operations.contract.updated';
        } else {
            $contract = Contract::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'operations.contract.created';
        }

        $this->auditLogger->record($event, $contract, $actor, $oldValues, $contract->fresh()?->toArray() ?? []);

        return $contract;
    }
}
