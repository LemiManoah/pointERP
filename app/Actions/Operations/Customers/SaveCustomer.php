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
     * @param  array{branch_id?: string|null, type: string, name: string, code?: string|null, email?: string|null, phone?: string|null, address?: string|null, status: string}  $data
     */
    public function handle(array $data, User $actor, ?Customer $customer = null): Customer
    {
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $data['branch_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'code' => $this->code($data['name'], $data['code'] ?? null, $customer),
            'email' => isset($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
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

    private function code(string $name, ?string $requestedCode, ?Customer $customer): string
    {
        $requested = Str::upper(Str::slug($requestedCode ?? '', '-'));
        $base = $requested !== '' ? $requested : Str::upper(Str::slug($name, '-'));
        $base = Str::limit($base !== '' ? $base : 'COMPANY', 60, '');
        $code = $base;
        $suffix = 2;

        $codeExists = function (string $candidate) use ($customer): bool {
            $query = Customer::query()->where('code', $candidate);
            if ($customer instanceof Customer) {
                $query->whereKeyNot($customer->id);
            }

            return $query->exists();
        };

        while ($codeExists($code)) {
            $ending = '-'.$suffix++;
            $code = Str::limit($base, 60 - mb_strlen($ending), '').$ending;
        }

        return $code;
    }
}
