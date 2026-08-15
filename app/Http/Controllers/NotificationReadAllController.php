<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class NotificationReadAllController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('notifications.view'), 403);

        $user->unreadNotifications()
            ->where('data->tenant_id', $user->tenant_id)
            ->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'All notifications marked as read.']);

        return back();
    }
}

