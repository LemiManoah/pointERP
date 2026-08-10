<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\CurrencySettings\ToggleTenantCurrency;
use App\Models\Currency;
use App\Models\TenantCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

final class TenantCurrencyController
{
    public function update(Currency $currency, ToggleTenantCurrency $action): RedirectResponse
    {
        Gate::authorize('viewAny', TenantCurrency::class);

        try {
            $setting = $action->handle($currency);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $setting->is_enabled ? 'Tenant currency enabled.' : 'Tenant currency disabled.',
        ]);

        return back();
    }
}
