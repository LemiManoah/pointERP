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
use Illuminate\Validation\ValidationException;
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

        return Inertia::render('operations/pos/index', ['sales' => $sales, ...$this->cartOptions($request, $options, $actor)]);
    }

    public function prepareCheckout(CompletePosSaleRequest $request, PosFormOptions $options): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', PosSale::class);
        $data = $request->validated();
        $existing = PosSale::query()->where('tenant_id', $actor->tenant_id)->where('checkout_key', $data['checkout_key'])->first();
        if ($existing instanceof PosSale) {
            Gate::authorize('view', $existing);

            return to_route('pos.show', $existing);
        }

        $request->merge(['store_id' => $data['inventory_store_id'], 'price_list_id' => $data['inventory_price_tier_id']]);
        $selected = $options->for($request, $actor)['selected'];
        if ($selected['branch_id'] !== $data['branch_id'] || $selected['store_id'] !== $data['inventory_store_id'] || $selected['price_list_id'] !== $data['inventory_price_tier_id']) {
            throw ValidationException::withMessages(['lines' => 'The sale location or price list is no longer available. Select it again.']);
        }

        $draft = collect($data)->only(['checkout_key', 'branch_id', 'inventory_store_id', 'inventory_price_tier_id', 'lines'])->all();
        $request->session()->put('pos_carts.'.$actor->tenant_id.'.'.$actor->id.'.'.$data['checkout_key'], $draft);

        return to_route('pos.checkout', ['cart' => $data['checkout_key']]);
    }

    public function checkout(Request $request, PosFormOptions $options): Response|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', PosSale::class);
        $props = $this->cartOptions($request, $options, $actor);
        if ($props['draft'] === null) {
            return to_route('pos.index');
        }

        return Inertia::render('operations/pos/checkout', $props);
    }

    public function store(CompletePosSaleRequest $request, CompletePosSale $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        Gate::authorize('create', PosSale::class);
        $sale = $action->handle($request->validated(), $actor);
        $request->session()->forget('pos_carts.'.$actor->tenant_id.'.'.$actor->id.'.'.$request->validated('checkout_key'));
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

    /** @return array<string, mixed> */
    private function cartOptions(Request $request, PosFormOptions $options, User $actor): array
    {
        $key = $request->string('cart')->toString();
        $draft = preg_match('/^[0-9a-f-]{36}$/i', $key) === 1
            ? $request->session()->get('pos_carts.'.$actor->tenant_id.'.'.$actor->id.'.'.$key)
            : null;
        if (is_array($draft)) {
            $request->merge(['branch_id' => $draft['branch_id'], 'store_id' => $draft['inventory_store_id'], 'price_list_id' => $draft['inventory_price_tier_id']]);
        }

        $props = $options->for($request, $actor);
        if (is_array($draft) && ($props['selected']['branch_id'] !== $draft['branch_id'] || $props['selected']['store_id'] !== $draft['inventory_store_id'] || $props['selected']['price_list_id'] !== $draft['inventory_price_tier_id'])) {
            $draft = null;
        }

        return [...$props, 'draft' => $draft, 'checkoutKey' => $draft['checkout_key'] ?? $props['checkoutKey']];
    }
}
