<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Pos\RecordPosPayment;
use App\Http\Requests\Operations\Pos\RecordPosPaymentRequest;
use App\Models\PosSale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class PosPaymentController
{
    public function __invoke(RecordPosPaymentRequest $request, PosSale $posSale, RecordPosPayment $action): RedirectResponse
    {
        Gate::authorize('recordPayment', $posSale);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $payment = $action->handle($posSale, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment '.$payment->payment_number.' recorded.']);

        return to_route('pos.show', $posSale);
    }
}
