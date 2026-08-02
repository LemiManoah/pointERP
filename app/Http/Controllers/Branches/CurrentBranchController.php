<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branches;

use App\Actions\Branches\SelectCurrentBranch;
use App\Http\Requests\Branches\SelectCurrentBranchRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class CurrentBranchController
{
    public function update(
        SelectCurrentBranchRequest $request,
        SelectCurrentBranch $action,
        #[CurrentUser] User $user,
    ): RedirectResponse {
        /** @var array{branch_id?: string|null} $data */
        $data = $request->validated();

        $branch = $action->handle($data['branch_id'] ?? null, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $branch instanceof Branch ? $branch->name.' selected.' : 'All branches selected.',
        ]);

        return back();
    }
}
