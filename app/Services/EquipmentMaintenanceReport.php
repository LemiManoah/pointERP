<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\EquipmentMaintenanceSchedule;
use App\Models\EquipmentMaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class EquipmentMaintenanceReport
{
    public function __construct(private BranchContext $branchContext, private TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{schedules: Collection<int, array<string, mixed>>, work_orders: Collection<int, array<string, mixed>>}
     */
    public function portfolio(User $user, array $filters = []): array
    {
        $branchIds = $this->branchContext->accessibleBranchIds($user);
        $search = is_string($filters['search'] ?? null) ? mb_trim($filters['search']) : null;
        $scheduleQuery = EquipmentMaintenanceSchedule::query()
            ->with(['equipment.branch', 'equipment.currentProject', 'equipment.currentSite', 'responsibleUser'])
            ->where('tenant_id', $this->tenantContext->id())
            ->where('is_active', true)
            ->whereIn('branch_id', $branchIds)
            ->when($filters['branch_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->where('branch_id', (string) $id))
            ->when($filters['equipment_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->where('equipment_id', (string) $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->whereHas('equipment', fn (Builder $query): Builder => $query->where('current_project_id', (string) $id)))
            ->when($filters['site_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->whereHas('equipment', fn (Builder $query): Builder => $query->where('current_site_id', (string) $id)))
            ->when($search, fn (Builder $query, string $term): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', '%'.$term.'%')
                ->orWhereHas('equipment', fn (Builder $query): Builder => $query->where('asset_code', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'))));
        $schedules = $scheduleQuery->get()
            ->filter(fn (EquipmentMaintenanceSchedule $schedule): bool => Gate::forUser($user)->allows('view', $schedule))
            ->map(fn (EquipmentMaintenanceSchedule $schedule): array => $this->scheduleRow($schedule))
            ->when($filters['due_status'] ?? null, fn (Collection $rows, mixed $status): Collection => $rows->where('due_status', (string) $status))
            ->sortBy(fn (array $row): int => match ($row['due_status']) {
                'overdue' => 0, 'due_soon' => 1, default => 2
            })
            ->values();

        $canViewCosts = $user->can('equipment.costs.view');
        $workOrders = EquipmentMaintenanceWorkOrder::query()
            ->with(['equipment.branch', 'schedule', 'provider', 'requestedBy'])
            ->withCount('documentLinks')
            ->where('tenant_id', $this->tenantContext->id())
            ->whereIn('branch_id', $branchIds)
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from): Builder => $query->whereDate('reported_at', '>=', (string) $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to): Builder => $query->whereDate('reported_at', '<=', (string) $to))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->where('branch_id', (string) $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->where('project_id', (string) $id))
            ->when($filters['site_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->where('site_id', (string) $id))
            ->when($filters['equipment_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->where('equipment_id', (string) $id))
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status): Builder => $query->where('status', (string) $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, mixed $priority): Builder => $query->where('priority', (string) $priority))
            ->when($search, fn (Builder $query, string $term): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('reference', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')
                ->orWhereHas('equipment', fn (Builder $query): Builder => $query->where('asset_code', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'))))
            ->latest('reported_at')->get()
            ->filter(fn (EquipmentMaintenanceWorkOrder $workOrder): bool => Gate::forUser($user)->allows('view', $workOrder))
            ->map(fn (EquipmentMaintenanceWorkOrder $workOrder): array => $this->workOrderRow($workOrder, $canViewCosts))
            ->values();

        return ['schedules' => $schedules, 'work_orders' => $workOrders];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $schedules
     * @param  Collection<int, array<string, mixed>>  $workOrders
     * @return array<string, mixed>
     */
    public function summary(Collection $schedules, Collection $workOrders): array
    {
        $costs = [];
        foreach ($workOrders as $row) {
            if ($row['total_cost'] === null) {
                continue;
            }

            if ($row['currency_code'] === null) {
                continue;
            }

            $currency = (string) $row['currency_code'];
            $costs[$currency] = ($costs[$currency] ?? 0.0) + (float) $row['total_cost'];
        }

        return [
            'due_soon' => $schedules->where('due_status', 'due_soon')->count(),
            'overdue' => $schedules->where('due_status', 'overdue')->count(),
            'planned' => $workOrders->whereIn('status', ['planned', 'approved'])->count(),
            'in_progress' => $workOrders->where('status', 'in_progress')->count(),
            'completed' => $workOrders->where('status', 'completed')->count(),
            'downtime_hours' => $workOrders->sum(fn (array $row): float => (float) ($row['downtime_hours'] ?? 0)),
            'costs_by_currency' => $costs,
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleRow(EquipmentMaintenanceSchedule $schedule): array
    {
        $equipment = $schedule->equipment;

        return [
            'id' => $schedule->id, 'equipment_id' => $equipment->id,
            'equipment_code' => $equipment->asset_code, 'equipment_name' => $equipment->name,
            'branch_id' => $schedule->branch_id, 'branch_name' => $equipment->branch->name,
            'project_id' => $equipment->current_project_id, 'project_name' => $equipment->currentProject?->name,
            'site_id' => $equipment->current_site_id, 'site_name' => $equipment->currentSite?->name,
            'name' => $schedule->name, 'maintenance_type' => $schedule->maintenance_type,
            'basis' => $schedule->basis, 'next_due_date' => $schedule->next_due_date?->toDateString(),
            'next_due_reading' => $schedule->next_due_reading, 'current_meter_reading' => $equipment->current_meter_reading,
            'responsible_user_name' => $schedule->responsibleUser?->name, 'due_status' => $schedule->dueStatus(),
        ];
    }

    /** @return array<string, mixed> */
    private function workOrderRow(EquipmentMaintenanceWorkOrder $workOrder, bool $canViewCosts): array
    {
        $provider = $workOrder->getRelation('provider');

        return [
            'id' => $workOrder->id, 'equipment_id' => $workOrder->equipment_id,
            'equipment_code' => $workOrder->equipment->asset_code, 'equipment_name' => $workOrder->equipment->name,
            'branch_id' => $workOrder->branch_id, 'branch_name' => $workOrder->equipment->branch->name,
            'project_id' => $workOrder->project_id, 'site_id' => $workOrder->site_id,
            'reference' => $workOrder->reference, 'schedule_name' => $workOrder->schedule?->name,
            'maintenance_type' => $workOrder->maintenance_type, 'priority' => $workOrder->priority,
            'description' => $workOrder->description, 'status' => $workOrder->status,
            'reported_at' => $workOrder->reported_at->toDateTimeString(),
            'planned_start_at' => $workOrder->planned_start_at?->toDateTimeString(),
            'actual_start_at' => $workOrder->actual_start_at?->toDateTimeString(),
            'completed_at' => $workOrder->completed_at?->toDateTimeString(),
            'provider_name' => $provider instanceof Customer ? $provider->name : $workOrder->provider_name,
            'downtime_hours' => $workOrder->downtime_hours,
            'total_cost' => $canViewCosts ? $workOrder->total_cost : null,
            'currency_code' => $canViewCosts ? $workOrder->currency_code : null,
            'requested_by' => $workOrder->requestedBy->name,
            'document_count' => (int) $workOrder->getAttribute('document_links_count'),
        ];
    }
}
