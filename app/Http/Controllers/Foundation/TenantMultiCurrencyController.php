<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Models\TenantCurrency;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class TenantMultiCurrencyController
{
    public function update(TenantContext $tenantContext, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('viewAny', TenantCurrency::class);

        $tenant = $tenantContext->current();
        $oldValues = [
            'multi_currency_enabled' => $tenant->multi_currency_enabled,
        ];

        $tenant->update([
            'multi_currency_enabled' => ! $tenant->multi_currency_enabled,
        ]);

        $auditLogger->record(
            event: $tenant->multi_currency_enabled ? 'currency.multi_currency.enabled' : 'currency.multi_currency.disabled',
            subject: $tenant,
            oldValues: $oldValues,
            newValues: [
                'multi_currency_enabled' => $tenant->multi_currency_enabled,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $tenant->multi_currency_enabled ? 'Multi-currency enabled.' : 'Multi-currency disabled.',
        ]);

        return back();
    }
}
