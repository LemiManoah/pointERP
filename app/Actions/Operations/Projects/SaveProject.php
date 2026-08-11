<?php

declare(strict_types=1);

namespace App\Actions\Operations\Projects;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveProject
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {
        //
    }

    /**
     * @param  array{branch_id: string, customer_id?: string|null, contract_id?: string|null, reference: string, name: string, description?: string|null, manager_id?: string|null, base_currency_code: string, budget_amount?: string|null, starts_on?: string|null, ends_on?: string|null, reporting_deadline?: string|null, status: string}  $data
     */
    public function handle(array $data, User $actor, ?Project $project = null): Project
    {
        $this->validateOptionalRelations($data);

        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'branch_id' => $data['branch_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
            'reference' => Str::upper($data['reference']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'base_currency_code' => Str::upper($data['base_currency_code']),
            'budget_amount' => $data['budget_amount'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'reporting_deadline' => $data['reporting_deadline'] ?? null,
            'status' => $data['status'],
            'updated_by' => $actor->id,
        ];

        $oldValues = $project instanceof Project ? $project->only(array_keys($attributes)) : [];

        if ($project instanceof Project) {
            $project->update($attributes);
            $event = 'operations.project.updated';
        } else {
            $project = Project::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'operations.project.created';
        }

        if (($data['manager_id'] ?? null) !== null) {
            $project->users()->syncWithoutDetaching([
                (string) $data['manager_id'] => ['role' => 'Project Manager', 'can_manage' => true],
            ]);
        }

        $this->auditLogger->record($event, $project, $actor, $oldValues, $project->fresh()?->toArray() ?? []);

        return $project;
    }

    /**
     * @param  array{branch_id: string, customer_id?: string|null, contract_id?: string|null, reference: string, name: string, description?: string|null, manager_id?: string|null, base_currency_code: string, budget_amount?: string|null, starts_on?: string|null, ends_on?: string|null, reporting_deadline?: string|null, status: string}  $data
     */
    private function validateOptionalRelations(array $data): void
    {
        if (($data['customer_id'] ?? null) !== null) {
            $customer = Customer::query()->whereKey($data['customer_id'])->firstOrFail();

            if ($customer->branch_id !== null && $customer->branch_id !== $data['branch_id']) {
                throw ValidationException::withMessages(['customer_id' => 'The customer is not available for that branch.']);
            }
        }

        if (($data['contract_id'] ?? null) !== null) {
            $contract = Contract::query()->whereKey($data['contract_id'])->firstOrFail();

            if ($contract->branch_id !== $data['branch_id']) {
                throw ValidationException::withMessages(['contract_id' => 'The contract does not belong to that branch.']);
            }
        }

        if (($data['manager_id'] ?? null) !== null) {
            $manager = User::query()->whereKey($data['manager_id'])->firstOrFail();

            if (! $manager->branches()->whereKey($data['branch_id'])->exists() && ! $manager->can('branches.view-all')) {
                throw ValidationException::withMessages(['manager_id' => 'The selected manager does not have access to that branch.']);
            }
        }
    }
}
