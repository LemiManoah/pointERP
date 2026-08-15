<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

final class NotificationReadController
{
    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('notifications.view'), 403);

        $record = $user->notifications()
            ->where('data->tenant_id', $user->tenant_id)
            ->findOrFail($notification);
        abort_unless($record instanceof DatabaseNotification, 404);

        $validated = $request->validate(['read' => ['required', 'boolean']]);

        $read = (bool) $validated['read'];

        if ($read) {
            $record->markAsRead();
        } else {
            $record->markAsUnread();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $read ? 'Notification marked as read.' : 'Notification marked as unread.']);

        return back();
    }
}
