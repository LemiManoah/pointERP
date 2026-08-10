<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Contracts\SaveContract;
use App\Http\Requests\Operations\Contracts\StoreContractRequest;
use App\Http\Requests\Operations\Contracts\UpdateContractRequest;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ContractController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Contract::class);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);

        return Inertia::render('operations/contracts/index', [
            'contracts' => Contract::query()
                ->with(['branch', 'customer'])
                ->where('tenant_id', $tenantId)
                ->visibleTo($user)
                ->orderBy('reference')
                ->get()
                ->map(fn (Contract $contract): array => [
                    'id' => $contract->id,
                    'branch_id' => $contract->branch_id,
                    'customer_id' => $contract->customer_id,
                    'branch_name' => $contract->branch->name,
                    'customer_name' => $contract->customer->name,
                    'reference' => $contract->reference,
                    'title' => $contract->title,
                    'scope_summary' => $contract->scope_summary,
                    'contract_value' => $contract->contract_value,
                    'currency_code' => $contract->currency_code,
                    'starts_on' => $contract->starts_on?->toDateString(),
                    'ends_on' => $contract->ends_on?->toDateString(),
                    'retention_percent' => $contract->retention_percent,
                    'payment_terms' => $contract->payment_terms,
                    'status' => $contract->status,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name]),
            'customers' => Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->visibleTo($user)
                ->orderBy('name')
                ->get(['id', 'name', 'branch_id'])
                ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->name, 'branch_id' => $customer->branch_id]),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => ['id' => $currency->code, 'name' => sprintf('%s - %s', $currency->code, $currency->name)]),
        ]);
    }

    public function store(StoreContractRequest $request, SaveContract $action): RedirectResponse
    {
        Gate::authorize('create', Contract::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, customer_id: string, reference: string, title: string, scope_summary?: string|null, contract_value?: string|null, currency_code: string, starts_on?: string|null, ends_on?: string|null, retention_percent?: string|null, payment_terms?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contract saved.']);

        return to_route('contracts.index');
    }

    public function update(UpdateContractRequest $request, Contract $contract, SaveContract $action): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id: string, customer_id: string, reference: string, title: string, scope_summary?: string|null, contract_value?: string|null, currency_code: string, starts_on?: string|null, ends_on?: string|null, retention_percent?: string|null, payment_terms?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $contract);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contract updated.']);

        return to_route('contracts.index');
    }

    public function destroy(Contract $contract, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $contract);

        $oldStatus = $contract->status;
        $newStatus = $oldStatus === 'archived' ? 'active' : 'archived';

        $contract->update(['status' => $newStatus]);
        $auditLogger->record(
            event: 'operations.contract.status_changed',
            subject: $contract,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contract status changed.']);

        return to_route('contracts.index');
    }
}
