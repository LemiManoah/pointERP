<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\EquipmentFuelTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class EquipmentFuelReport
{
    public function __construct(private BranchContext $branchContext, private TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(User $user, array $filters = []): Collection
    {
        $branchIds = $this->branchContext->accessibleBranchIds($user);
        $canViewCosts = $user->can('equipment.costs.view');

        return EquipmentFuelTransaction::query()
            ->with(['equipment', 'branch', 'project', 'site', 'provider', 'receiver', 'submittedBy', 'approvedBy'])
            ->where('tenant_id', $this->tenantContext->id())
            ->whereIn('branch_id', $branchIds)
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from): Builder => $query->whereDate('transacted_at', '>=', (string) $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to): Builder => $query->whereDate('transacted_at', '<=', (string) $to))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, mixed $branchId): Builder => $query->where('branch_id', (string) $branchId))
            ->when($filters['project_id'] ?? null, fn (Builder $query, mixed $projectId): Builder => $query->where('project_id', (string) $projectId))
            ->when($filters['site_id'] ?? null, fn (Builder $query, mixed $siteId): Builder => $query->where('site_id', (string) $siteId))
            ->when($filters['equipment_id'] ?? null, fn (Builder $query, mixed $equipmentId): Builder => $query->where('equipment_id', (string) $equipmentId))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, mixed $type): Builder => $query->where('transaction_type', (string) $type))
            ->when($filters['source_type'] ?? null, fn (Builder $query, mixed $source): Builder => $query->where('source_type', (string) $source))
            ->when($filters['exception_status'] ?? null, fn (Builder $query, mixed $exception): Builder => $query->where('exception_status', (string) $exception))
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status): Builder => $query->where('status', (string) $status))
            ->when($filters['search'] ?? null, function (Builder $query, mixed $search): void {
                $term = '%'.mb_trim((string) $search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('voucher_reference', 'like', $term)
                        ->orWhere('source_name', 'like', $term)
                        ->orWhereHas('equipment', fn (Builder $query): Builder => $query
                            ->where('asset_code', 'like', $term)
                            ->orWhere('name', 'like', $term));
                });
            })
            ->latest('transacted_at')
            ->latest('created_at')
            ->get()
            ->filter(fn (EquipmentFuelTransaction $transaction): bool => Gate::forUser($user)->allows('view', $transaction))
            ->map(fn (EquipmentFuelTransaction $transaction): array => $this->row($transaction, $user, $canViewCosts))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function summary(Collection $rows): array
    {
        $quantityByFuel = [];
        $costsByCurrency = [];

        foreach ($rows as $row) {
            $fuelType = (string) $row['fuel_type'];
            $quantityByFuel[$fuelType] = ($quantityByFuel[$fuelType] ?? 0.0) + (float) $row['quantity'];
            if ($row['total_cost'] === null) {
                continue;
            }
            if ($row['currency_code'] === null) {
                continue;
            }

            $currencyCode = (string) $row['currency_code'];
            $costsByCurrency[$currencyCode] = ($costsByCurrency[$currencyCode] ?? 0.0) + (float) $row['total_cost'];
        }

        return [
            'transactions' => $rows->count(),
            'assets' => $rows->pluck('equipment_id')->unique()->count(),
            'review_required' => $rows->where('exception_status', 'review_required')->count(),
            'insufficient_evidence' => $rows->where('exception_status', 'insufficient_evidence')->count(),
            'quantity_by_fuel' => $quantityByFuel,
            'costs_by_currency' => $costsByCurrency,
        ];
    }

    /** @return array<string, mixed> */
    private function row(EquipmentFuelTransaction $transaction, User $user, bool $canViewCosts): array
    {
        $provider = $transaction->getRelation('provider');
        $receiver = $transaction->getRelation('receiver');

        return [
            'id' => $transaction->id, 'equipment_id' => $transaction->equipment_id,
            'equipment_code' => $transaction->equipment->asset_code, 'equipment_name' => $transaction->equipment->name,
            'branch_id' => $transaction->branch_id, 'branch_name' => $transaction->branch->name,
            'project_id' => $transaction->project_id, 'project_name' => $transaction->project?->name,
            'site_id' => $transaction->site_id, 'site_name' => $transaction->site?->name,
            'transacted_at' => $transaction->transacted_at->toDateTimeString(),
            'transaction_type' => $transaction->transaction_type, 'fuel_type' => $transaction->fuel_type,
            'quantity' => $transaction->quantity, 'unit' => $transaction->unit,
            'source_type' => $transaction->source_type,
            'source_name' => $provider instanceof Customer ? $provider->name : $transaction->source_name,
            'receiver_name' => $receiver instanceof Staff ? $receiver->name : null,
            'voucher_reference' => $transaction->voucher_reference,
            'meter_reading' => $transaction->meter_reading,
            'tank_level_before' => $transaction->tank_level_before,
            'tank_level_after' => $transaction->tank_level_after,
            'is_full_tank' => $transaction->is_full_tank,
            'notes' => $transaction->notes,
            'unit_cost' => $canViewCosts ? $transaction->unit_cost : null,
            'total_cost' => $canViewCosts ? $transaction->total_cost : null,
            'currency_code' => $canViewCosts ? $transaction->currency_code : null,
            'status' => $transaction->status, 'exception_status' => $transaction->exception_status,
            'exception_reason' => $transaction->exception_reason,
            'reversal_of_id' => $transaction->reversal_of_id,
            'reversal_reason' => $transaction->reversal_reason,
            'submitted_by' => $transaction->submittedBy->name,
            'approved_by' => $transaction->approvedBy?->name,
            'can_approve' => Gate::forUser($user)->allows('approve', $transaction),
            'can_reverse' => Gate::forUser($user)->allows('reverse', $transaction) && ! $transaction->reversals()->exists(),
        ];
    }
}
