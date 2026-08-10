<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\ExchangeRates\ApproveExchangeRate;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

final class ExchangeRateApprovalController
{
    public function store(ExchangeRate $exchangeRate, ApproveExchangeRate $action): RedirectResponse
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
}
