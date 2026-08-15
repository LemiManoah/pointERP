<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('notifications.view'), 403);

        $notifications = $user->notifications()
            ->where('data->tenant_id', $user->tenant_id)
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'category' => $notification->data['category'] ?? 'general',
                'severity' => $notification->data['severity'] ?? 'info',
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'action_url' => $notification->data['action_url'] ?? null,
                'read_at' => $notification->read_at?->toDateTimeString(),
                'created_at' => $notification->created_at?->toDateTimeString() ?? '',
            ]);
        $preference = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['tenant_id' => $user->tenant_id],
        );

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'preference' => [
                'email_enabled' => $preference->email_enabled,
                'muted_email_categories' => $preference->muted_email_categories ?? [],
                'digest_frequency' => $preference->digest_frequency,
            ],
        ]);
    }
}
