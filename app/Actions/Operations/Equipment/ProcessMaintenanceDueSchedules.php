<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\EquipmentMaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OperationalNotificationSender;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class ProcessMaintenanceDueSchedules
{
    public function __construct(private TenantContext $tenantContext, private OperationalNotificationSender $notifications) {}

    /** @return array{due_soon: int, overdue: int, notifications: int} */
    public function handle(CarbonImmutable $asOf, ?string $tenantId = null): array
    {
        $result = ['due_soon' => 0, 'overdue' => 0, 'notifications' => 0];
        $tenants = Tenant::query()->active()->when($tenantId, fn (Builder $query, string $id): Builder => $query->whereKey($id))->get();

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);
            $schedules = EquipmentMaintenanceSchedule::query()->with(['equipment', 'responsibleUser'])
                ->where('is_active', true)->get();

            foreach ($schedules as $schedule) {
                $status = $schedule->dueStatus($asOf);
                if (! in_array($status, ['due_soon', 'overdue'], true)) {
                    if ($schedule->last_notified_status !== null) {
                        $schedule->forceFill(['last_notified_status' => null, 'last_notified_at' => null])->save();
                    }

                    continue;
                }

                $result[$status]++;
                $recentlyNotified = $schedule->last_notified_status === $status
                    && $schedule->last_notified_at !== null
                    && $schedule->last_notified_at->gte($asOf->subDays(7));
                if ($recentlyNotified) {
                    continue;
                }

                $recipients = User::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get()
                    ->filter(fn (User $user): bool => $user->id === $schedule->responsible_user_id
                        || ($user->can('equipment.maintenance.approve')
                            && ($user->can('branches.view-all') || $user->branches()->whereKey($schedule->branch_id)->exists())))
                    ->unique('id')->values();
                $this->notifications->send($recipients, [
                    'tenant_id' => $schedule->tenant_id, 'branch_id' => $schedule->branch_id,
                    'equipment_id' => $schedule->equipment_id, 'equipment_maintenance_schedule_id' => $schedule->id,
                    'category' => 'equipment_maintenance', 'severity' => $status === 'overdue' ? 'critical' : 'warning',
                    'title' => $status === 'overdue' ? 'Equipment maintenance overdue' : 'Equipment maintenance due soon',
                    'message' => sprintf('%s: %s is %s.', $schedule->equipment->asset_code, $schedule->name, str_replace('_', ' ', $status)),
                    'action_url' => '/equipment/'.$schedule->equipment_id.'?tab=maintenance',
                ]);
                $schedule->forceFill(['last_notified_status' => $status, 'last_notified_at' => $asOf])->save();
                $result['notifications'] += $recipients->count();
            }
        }

        return $result;
    }
}
