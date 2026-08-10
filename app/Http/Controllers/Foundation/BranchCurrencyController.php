<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\CurrencySettings\SaveBranchCurrency;
use App\Http\Requests\Foundation\CurrencySettings\SaveBranchCurrencyRequest;
use App\Models\BranchCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

final class BranchCurrencyController
{
    public function store(SaveBranchCurrencyRequest $request, SaveBranchCurrency $action): RedirectResponse
    {
        Gate::authorize('viewAny', BranchCurrency::class);

        /** @var array{branch_id: string, currency_code: string, is_enabled: bool, is_default_transaction_currency: bool, can_receive: bool, can_pay: bool} $data */
        $data = $request->validated();

        try {
            $action->handle($data);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Branch currency setting saved.']);

        return back();
    }
}
