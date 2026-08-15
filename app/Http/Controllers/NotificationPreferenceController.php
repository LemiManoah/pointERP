<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class NotificationPreferenceController
{
    public function update(UpdateNotificationPreferenceRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('notifications.manage-preferences'), 403);

        $preference = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['tenant_id' => $user->tenant_id],
        );
        $oldValues = $preference->toArray();
        $preference->fill($request->validated())->save();

        $auditLogger->record(
            'operations.notification.preference_updated',
            $user,
            $user,
            $oldValues,
            $preference->fresh()?->toArray() ?? [],
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notification preferences updated.']);

        return back();
    }
}
