<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workforce\SaveWorkforceTrade;
use App\Http\Requests\Operations\Workforce\StoreWorkforceTradeRequest;
use App\Http\Requests\Operations\Workforce\UpdateWorkforceTradeRequest;
use App\Models\User;
use App\Models\WorkforceTrade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class WorkforceTradeController
{
    public function store(StoreWorkforceTradeRequest $request, SaveWorkforceTrade $action): RedirectResponse
    {
        Gate::authorize('create', WorkforceTrade::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{code?: string|null, name: string, category: string, is_active: bool} $data */
        $data = $request->validated();
        $action->handle($data, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trade created.']);

        return to_route('workforce.index', ['tab' => 'trades']);
    }

    public function update(UpdateWorkforceTradeRequest $request, WorkforceTrade $workforceTrade, SaveWorkforceTrade $action): RedirectResponse
    {
        Gate::authorize('update', $workforceTrade);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{code?: string|null, name: string, category: string, is_active: bool} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $workforceTrade);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trade updated.']);

        return to_route('workforce.index', ['tab' => 'trades']);
    }

    public function destroy(WorkforceTrade $workforceTrade, SaveWorkforceTrade $action): RedirectResponse
    {
        Gate::authorize('delete', $workforceTrade);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $action->handle([
            'code' => $workforceTrade->code,
            'name' => $workforceTrade->name,
            'category' => $workforceTrade->category->value,
            'is_active' => ! $workforceTrade->is_active,
        ], $actor, $workforceTrade);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $workforceTrade->is_active ? 'Trade restored.' : 'Trade deactivated.',
        ]);

        return to_route('workforce.index', ['tab' => 'trades']);
    }
}
