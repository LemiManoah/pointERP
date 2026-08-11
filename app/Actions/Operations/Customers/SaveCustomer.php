<?php

declare(strict_types=1);

namespace App\Actions\Operations\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;

final readonly class SaveCustomer
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {
        //
    }

    /**
     * @param  array{branch_id?: string|null, type: string, name: string, code: string, email?: string|null, phone?: string|null, tax_number?: string|null, address?: string|null, status: string}  $data
     */
    public function handle(array $data, User $actor, ?Customer $customer = null): Customer
    {
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $data['branch_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => Str::upper($data['code']),
            'email' => isset($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'updated_by' => $actor->id,
        ];

        $oldValues = $customer instanceof Customer ? $customer->only(array_keys($attributes)) : [];

        if ($customer instanceof Customer) {
            $customer->update($attributes);
            $event = 'operations.customer.updated';
        } else {
            $customer = Customer::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'operations.customer.created';
        }

        $this->auditLogger->record($event, $customer, $actor, $oldValues, $customer->fresh()?->toArray() ?? []);

        return $customer;
    }
}
