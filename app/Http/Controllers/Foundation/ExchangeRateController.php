<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\ExchangeRates\ApproveExchangeRate;
use App\Actions\Foundation\ExchangeRates\DeleteDraftExchangeRate;
use App\Actions\Foundation\ExchangeRates\SaveExchangeRate;
use App\Http\Requests\Foundation\ExchangeRates\StoreExchangeRateRequest;
use App\Http\Requests\Foundation\ExchangeRates\UpdateExchangeRateRequest;
use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class ExchangeRateController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ExchangeRate::class);

        $tenantId = resolve(TenantContext::class)->id();
        $branchContext = resolve(BranchContext::class);
        $accessibleBranchIds = $branchContext->accessibleBranchIds();
        $canViewAllBranches = $branchContext->canViewAllBranches();

        return Inertia::render('foundation/exchange-rates/index', [
            'exchangeRates' => ExchangeRate::query()
                ->with('branch')
                ->where('tenant_id', $tenantId)
                ->unless(
                    $canViewAllBranches,
                    fn ($query) => $query->where(fn ($query) => $query
                        ->whereNull('branch_id')
                        ->orWhereIn('branch_id', $accessibleBranchIds)),
                )
                ->latest('effective_date')
                ->latest()
                ->get()
                ->map(fn (ExchangeRate $rate): array => [
                    'id' => $rate->id,
                    'branch_id' => $rate->branch_id,
                    'branch_name' => $rate->branch?->name,
                    'from_currency_code' => $rate->from_currency_code,
                    'to_currency_code' => $rate->to_currency_code,
                    'rate' => $rate->rate,
                    'effective_date' => $rate->effective_date->toDateString(),
                    'expires_at' => $rate->expires_at?->toDateTimeString(),
                    'status' => $rate->status,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->unless($canViewAllBranches, fn ($query) => $query->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ]),
            'currencies' => TenantCurrency::query()
                ->with('currency')
                ->where('tenant_id', $tenantId)
                ->where('is_enabled', true)
                ->orderBy('currency_code')
                ->get()
                ->map(fn (TenantCurrency $setting): array => [
                    'code' => $setting->currency_code,
                    'name' => $setting->currency->name,
                ]),
        ]);
    }

    public function store(StoreExchangeRateRequest $request, SaveExchangeRate $action): RedirectResponse
    {
        Gate::authorize('create', ExchangeRate::class);

        /** @var User $user */
        $user = $request->user();
        /** @var array{branch_id?: string|null, from_currency_code: string, to_currency_code: string, rate: int|float|string, effective_date: string, expires_at?: string|null} $data */
        $data = $request->validated();

        try {
            $action->handle($data, $user);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft exchange rate created.']);

        return to_route('foundation.exchange-rates.index');
    }

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate, SaveExchangeRate $action): RedirectResponse
    {
        Gate::authorize('update', $exchangeRate);

        /** @var User $user */
        $user = $request->user();
        /** @var array{branch_id?: string|null, from_currency_code: string, to_currency_code: string, rate: int|float|string, effective_date: string, expires_at?: string|null} $data */
        $data = $request->validated();

        try {
            $action->handle($data, $user, $exchangeRate);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft exchange rate updated.']);

        return to_route('foundation.exchange-rates.index');
    }

    public function approve(ExchangeRate $exchangeRate, ApproveExchangeRate $action): RedirectResponse
    {
        Gate::authorize('approve', $exchangeRate);

        /** @var User $user */
        $user = auth()->user();

        try {
            $action->handle($exchangeRate, $user);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Exchange rate approved.']);

        return back();
    }

    public function destroy(ExchangeRate $exchangeRate, DeleteDraftExchangeRate $action): RedirectResponse
    {
        Gate::authorize('delete', $exchangeRate);

        try {
            $action->handle($exchangeRate);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft exchange rate deleted.']);

        return back();
    }
}
