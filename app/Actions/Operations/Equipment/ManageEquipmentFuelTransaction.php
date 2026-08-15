<?php

declare(strict_types=1);

namespace App\Actions\Operations\Equipment;

use App\Models\BranchCurrency;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentFuelTransaction;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EquipmentFuelNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ManageEquipmentFuelTransaction
{
    public function __construct(
        private RecordEquipmentMeterReading $recordMeterReading,
        private AuditLogger $auditLogger,
        private EquipmentFuelNotificationService $notificationService,
    ) {}

    /** @param array<string, mixed> $data */
    public function submit(Equipment $equipment, array $data, User $actor): EquipmentFuelTransaction
    {
        return DB::transaction(function () use ($actor, $data, $equipment): EquipmentFuelTransaction {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if (! $equipment->is_active || in_array($equipment->current_status, ['retired', 'transferred'], true)) {
                throw ValidationException::withMessages(['equipment' => 'Fuel cannot be recorded while equipment is retired or in transit.']);
            }

            $transactedAt = CarbonImmutable::parse((string) $data['transacted_at']);
            if ($transactedAt->isAfter(now()->addMinutes(5))) {
                throw ValidationException::withMessages(['transacted_at' => 'Fuel transaction time cannot be in the future.']);
            }

            $provider = isset($data['provider_customer_id'])
                ? Customer::query()->where('status', 'active')->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->find($data['provider_customer_id'])
                : null;
            if (($data['source_type'] ?? null) === 'supplier' && ! $provider instanceof Customer && blank($data['source_name'] ?? null)) {
                throw ValidationException::withMessages(['provider_customer_id' => 'Select a supplier or enter the external fuel source name.']);
            }

            $receiver = isset($data['received_by_staff_id'])
                ? Staff::query()->where('branch_id', $equipment->branch_id)->where('status', 'active')->find($data['received_by_staff_id'])
                : null;
            if (isset($data['received_by_staff_id']) && ! $receiver instanceof Staff) {
                throw ValidationException::withMessages(['received_by_staff_id' => 'Select an active receiving staff member in the equipment branch.']);
            }

            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : null;
            if ($unitCost !== null && ! $actor->can('equipment.costs.view')) {
                throw ValidationException::withMessages(['unit_cost' => 'You do not have permission to record equipment costs.']);
            }

            $currencyCode = $unitCost === null ? null : mb_strtoupper((string) ($data['currency_code'] ?? $equipment->branch->default_currency_code));
            if ($currencyCode !== null && ! BranchCurrency::query()->where('branch_id', $equipment->branch_id)->where('currency_code', $currencyCode)->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages(['currency_code' => 'The selected currency is not enabled for this branch.']);
            }

            $quantity = (float) $data['quantity'];
            $sourceName = $provider instanceof Customer ? $provider->name : ($data['source_name'] ?? null);
            $transaction = EquipmentFuelTransaction::query()->create([
                'tenant_id' => $equipment->tenant_id, 'equipment_id' => $equipment->id, 'branch_id' => $equipment->branch_id,
                'project_id' => $equipment->current_project_id, 'site_id' => $equipment->current_site_id,
                'equipment_location_id' => $equipment->current_location_id, 'transacted_at' => $transactedAt,
                'transaction_type' => $data['transaction_type'], 'fuel_type' => $data['fuel_type'],
                'quantity' => number_format($quantity, 4, '.', ''), 'unit' => 'litre', 'source_type' => $data['source_type'],
                'provider_customer_id' => $provider?->id, 'source_name' => $sourceName,
                'unit_cost' => $unitCost === null ? null : number_format($unitCost, 4, '.', ''),
                'total_cost' => $unitCost === null ? null : number_format($quantity * $unitCost, 4, '.', ''),
                'currency_code' => $currencyCode, 'meter_reading' => $data['meter_reading'] ?? null,
                'tank_level_before' => $data['tank_level_before'] ?? null, 'tank_level_after' => $data['tank_level_after'] ?? null,
                'is_full_tank' => (bool) $data['is_full_tank'], 'issued_by_user_id' => $actor->id,
                'received_by_staff_id' => $receiver?->id, 'voucher_reference' => $data['voucher_reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'exception_status' => 'not_evaluated',
                'status' => EquipmentFuelTransaction::STATUS_SUBMITTED, 'submitted_by' => $actor->id,
                'submitted_at' => now(), 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $this->auditLogger->record('equipment.fuel.submitted', $transaction, $actor, [], $transaction->toArray());
            DB::afterCommit(fn () => $this->notificationService->submitted($transaction));

            return $transaction->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function approve(EquipmentFuelTransaction $transaction, array $data, User $actor): EquipmentFuelTransaction
    {
        return DB::transaction(function () use ($actor, $data, $transaction): EquipmentFuelTransaction {
            $transaction = EquipmentFuelTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($transaction->equipment_id);
            if ($transaction->status !== EquipmentFuelTransaction::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['fuel_transaction' => 'Only a submitted fuel transaction can be approved.']);
            }

            if (in_array($transaction->transaction_type, ['issue', 'refuel'], true) && blank($transaction->voucher_reference)) {
                throw ValidationException::withMessages(['voucher_reference' => 'A voucher or delivery reference is required before posting fuel issued to equipment.']);
            }

            if ($equipment->meter_type !== 'none' && $transaction->meter_reading !== null) {
                $this->recordMeterReading->handle($equipment, [
                    'reading_value' => $transaction->meter_reading, 'read_at' => $transaction->transacted_at,
                    'project_id' => $transaction->project_id, 'site_id' => $transaction->site_id,
                    'equipment_location_id' => $transaction->equipment_location_id, 'event_type' => 'fuel',
                    'evidence_note' => 'Posted fuel transaction '.$transaction->voucher_reference,
                ], $actor);
            }

            $oldValues = $transaction->only(['status', 'approved_by', 'approved_at', 'posted_by', 'posted_at']);
            $transaction->forceFill([
                'status' => EquipmentFuelTransaction::STATUS_POSTED, 'approved_by' => $actor->id,
                'approved_at' => now(), 'posted_by' => $actor->id, 'posted_at' => now(), 'updated_by' => $actor->id,
            ])->save();
            $this->auditLogger->record('equipment.fuel.posted', $transaction, $actor, $oldValues, $transaction->only(['status', 'approved_by', 'approved_at', 'posted_by', 'posted_at']), isset($data['approval_note']) ? (string) $data['approval_note'] : null);
            DB::afterCommit(fn () => $this->notificationService->reviewed($transaction, false));

            return $transaction->refresh();
        });
    }

    public function reverse(EquipmentFuelTransaction $transaction, string $reason, User $actor): EquipmentFuelTransaction
    {
        return DB::transaction(function () use ($actor, $reason, $transaction): EquipmentFuelTransaction {
            $transaction = EquipmentFuelTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status !== EquipmentFuelTransaction::STATUS_POSTED || $transaction->reversals()->exists()) {
                throw ValidationException::withMessages(['fuel_transaction' => 'Only an unreversed posted fuel transaction can be reversed.']);
            }

            $reversal = $transaction->replicate([
                'status', 'reversal_of_id', 'reversal_reason', 'submitted_by', 'submitted_at',
                'approved_by', 'approved_at', 'posted_by', 'posted_at', 'reversed_by', 'reversed_at',
                'created_by', 'updated_by', 'created_at', 'updated_at',
            ]);
            $reversal->forceFill([
                'id' => (string) str()->uuid(), 'transaction_type' => 'adjustment',
                'quantity' => number_format(-1 * (float) $transaction->quantity, 4, '.', ''),
                'total_cost' => $transaction->total_cost === null ? null : number_format(-1 * (float) $transaction->total_cost, 4, '.', ''),
                'meter_reading' => null, 'status' => EquipmentFuelTransaction::STATUS_POSTED,
                'daily_site_report_equipment_line_id' => null,
                'reversal_of_id' => $transaction->id, 'reversal_reason' => $reason,
                'submitted_by' => $actor->id, 'submitted_at' => now(), 'approved_by' => $actor->id,
                'approved_at' => now(), 'posted_by' => $actor->id, 'posted_at' => now(),
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ])->save();

            $transaction->forceFill([
                'status' => EquipmentFuelTransaction::STATUS_REVERSED,
                'reversed_by' => $actor->id, 'reversed_at' => now(), 'updated_by' => $actor->id,
            ])->save();
            $this->auditLogger->record('equipment.fuel.reversed', $transaction, $actor, ['status' => EquipmentFuelTransaction::STATUS_POSTED], ['status' => EquipmentFuelTransaction::STATUS_REVERSED, 'reversal_id' => $reversal->id], $reason);
            DB::afterCommit(fn () => $this->notificationService->reviewed($transaction, true));

            return $reversal->refresh();
        });
    }
}
