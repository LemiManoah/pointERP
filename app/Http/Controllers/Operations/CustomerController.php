<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Customers\SaveCustomer;
use App\Http\Requests\Operations\Customers\StoreCustomerRequest;
use App\Http\Requests\Operations\Customers\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($user);

        return Inertia::render('operations/customers/index', [
            'customers' => Customer::query()
                ->with('branch')
                ->where('tenant_id', $tenantId)
                ->visibleTo($user)
                ->orderBy('name')
                ->get()
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'branch_id' => $customer->branch_id,
                    'branch_name' => $customer->branch?->name,
                    'type' => $customer->type,
                    'name' => $customer->name,
                    'code' => $customer->code,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'tax_number' => $customer->tax_number,
                    'address' => $customer->address,
                    'status' => $customer->status,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $branchIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name]),
        ]);
    }

    public function store(StoreCustomerRequest $request, SaveCustomer $action): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id?: string|null, type: string, name: string, code: string, email?: string|null, phone?: string|null, tax_number?: string|null, address?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company saved.']);

        return to_route('customers.index');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, SaveCustomer $action): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{branch_id?: string|null, type: string, name: string, code: string, email?: string|null, phone?: string|null, tax_number?: string|null, address?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $customer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company updated.']);

        return to_route('customers.index');
    }

    public function destroy(Customer $customer, SaveCustomer $action): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle([
            'branch_id' => $customer->branch_id,
            'type' => $customer->type,
            'name' => $customer->name,
            'code' => $customer->code,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'tax_number' => $customer->tax_number,
            'address' => $customer->address,
            'status' => $customer->status === 'active' ? 'inactive' : 'active',
        ], $actor, $customer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company status changed.']);

        return to_route('customers.index');
    }
}
