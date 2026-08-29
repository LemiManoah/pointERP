<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Pos\CompletePosSale;
use App\Enums\PosPaymentMethod;
use App\Http\Requests\Operations\Pos\CompletePosSaleRequest;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\PosSaleLine;
use App\Models\PosSaleLineAllocation;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\PosFormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PosSaleController
{
    public function index(Request $request, PosFormOptions $options, BranchContext $branches): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('viewAny', PosSale::class);
        $branchIds = $branches->accessibleBranchIds($actor);

        $sales = PosSale::query()
            ->whereIn('branch_id', $branchIds)
            ->unless($actor->can('pos.view-all-sales'), fn (Builder $query): Builder => $query->where('sold_by', $actor->id))
            ->with(['customer', 'store', 'seller', 'payments'])
            ->withCount('lines')
            ->latest('completed_at')
            ->limit(200)
            ->get()
            ->map(fn (PosSale $sale): array => [
                'id' => $sale->id, 'sale_number' => $sale->sale_number,
                'customer' => $sale->customer_id !== null ? $sale->customer->name : 'Walk-in customer', 'store' => $sale->store->name,
                'cashier' => $sale->seller->name, 'status' => $sale->status->value, 'status_label' => $sale->status->label(),
                'currency_code' => $sale->currency_code, 'total_amount' => $sale->total_amount,
                'amount_paid' => $sale->amount_paid, 'balance_due' => $sale->balance_due,
                'payment_status' => $sale->payment_status->value, 'payment_status_label' => $sale->payment_status->label(),
                'line_count' => $sale->lines_count, 'completed_at' => $sale->completed_at?->toISOString(),
                'payments' => $actor->can('pos.view-payments') ? $sale->payments->map(fn (PosPayment $payment): array => ['method' => $payment->method->label(), 'amount' => $payment->amount])->values() : [],
            ]);

        return Inertia::render('operations/pos/index', ['sales' => $sales, ...$options->for($request, $actor)]);
    }

    public function store(CompletePosSaleRequest $request, CompletePosSale $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', PosSale::class);
        $sale = $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sale completed under receipt '.$sale->sale_number.'.']);

        return to_route('pos.show', $sale);
    }

    public function show(Request $request, PosSale $posSale): Response
    {
        Gate::authorize('view', $posSale);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $posSale->load(['branch', 'store', 'customer', 'seller', 'lines.allocations.batch', 'payments.recorder']);

        return Inertia::render('operations/pos/show', [
            'sale' => [
                'id' => $posSale->id, 'sale_number' => $posSale->sale_number, 'status' => $posSale->status->value,
                'status_label' => $posSale->status->label(), 'branch' => $posSale->branch->name, 'store' => $posSale->store->name,
                'customer' => $posSale->customer_id !== null ? $posSale->customer->name : 'Walk-in customer', 'cashier' => $posSale->seller->name,
                'currency_code' => $posSale->currency_code, 'subtotal' => $posSale->subtotal, 'discount_total' => $posSale->discount_total,
                'total_amount' => $posSale->total_amount, 'amount_paid' => $posSale->amount_paid, 'balance_due' => $posSale->balance_due,
                'payment_status' => $posSale->payment_status->value, 'payment_status_label' => $posSale->payment_status->label(),
                'notes' => $posSale->notes, 'completed_at' => $posSale->completed_at?->toISOString(),
                'lines' => $posSale->lines->map(fn (PosSaleLine $line): array => ['id' => $line->id, 'code' => $line->item_code_snapshot, 'name' => $line->item_name_snapshot, 'quantity' => $line->quantity, 'unit' => $line->unit_symbol_snapshot, 'unit_price' => $line->unit_price, 'discount' => $line->discount_amount, 'total' => $line->line_total, 'batches' => $line->allocations->filter(fn (PosSaleLineAllocation $allocation): bool => $allocation->batch_number_snapshot !== null)->pluck('batch_number_snapshot')->join(', ')]),
                'payments' => $actor->can('pos.view-payments') ? $posSale->payments->map(fn (PosPayment $payment): array => ['number' => $payment->payment_number, 'method' => $payment->method->label(), 'amount' => $payment->amount, 'reference' => $payment->reference, 'recorded_at' => $payment->recorded_at->toISOString()]) : [],
            ],
            'can' => ['recordPayment' => Gate::forUser($actor)->allows('recordPayment', $posSale)],
            'paymentMethods' => collect(PosPaymentMethod::cases())->map(fn (PosPaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])->values(),
        ]);
    }
}
