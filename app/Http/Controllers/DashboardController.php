<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildDashboard;
use App\Http\Requests\DashboardRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(DashboardRequest $request, BuildDashboard $dashboard): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('dashboard', $dashboard->handle($user, $request->validated()));
    }
}
