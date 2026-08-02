<?php

declare(strict_types=1);

namespace App\Http\Controllers\Resources;

use App\Actions\Resources\StaffPositions\SaveStaffPosition;
use App\Actions\Resources\StaffPositions\ToggleStaffPositionStatus;
use App\Http\Requests\Resources\StaffPositions\StoreStaffPositionRequest;
use App\Http\Requests\Resources\StaffPositions\UpdateStaffPositionRequest;
use App\Models\StaffPosition;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class StaffPositionController
{
    public function index(): Response
    {
        $tenantId = resolve(TenantContext::class)->id();

        return Inertia::render('resources/staff-positions/index', [
            'positions' => StaffPosition::query()
                ->withCount('staff')
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get()
                ->map(fn (StaffPosition $position): array => [
                    'id' => $position->id,
                    'name' => $position->name,
                    'code' => $position->code,
                    'is_active' => $position->is_active,
                    'staff_count' => (int) $position->getAttribute('staff_count'),
                ]),
        ]);
    }

    public function store(StoreStaffPositionRequest $request, SaveStaffPosition $action): RedirectResponse
    {
        /** @var array{name: string, code: string, is_active?: bool} $data */
        $data = $request->validated();

        $action->handle($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Staff position created.',
        ]);

        return to_route('resources.staff-positions.index');
    }

    public function update(UpdateStaffPositionRequest $request, StaffPosition $staffPosition, SaveStaffPosition $action): RedirectResponse
    {
        abort_unless($staffPosition->tenant_id === resolve(TenantContext::class)->id(), 404);

        /** @var array{name: string, code: string, is_active?: bool} $data */
        $data = $request->validated();

        $action->handle($data, $staffPosition);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Staff position updated.',
        ]);

        return to_route('resources.staff-positions.index');
    }

    public function destroy(StaffPosition $staffPosition, ToggleStaffPositionStatus $action): RedirectResponse
    {
        abort_unless($staffPosition->tenant_id === resolve(TenantContext::class)->id(), 404);

        $action->handle($staffPosition);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $staffPosition->is_active ? 'Staff position activated.' : 'Staff position deactivated.',
        ]);

        return to_route('resources.staff-positions.index');
    }
}
