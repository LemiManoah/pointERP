<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryReservationStatus;
use App\Enums\MaterialRequisitionStatus;
use App\Models\InventoryReservation;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use App\Services\AuditLogger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

final readonly class CancelMaterialRequisition
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(MaterialRequisition $requisition, string $reason, User $actor): MaterialRequisition
    {
        return DB::transaction(function () use ($actor, $reason, $requisition): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            $oldStatus = $requisition->status->value;
            $reservations = InventoryReservation::query()->where('source_type', MaterialRequisitionLine::class)->whereIn('source_id', $requisition->lines()->pluck('id'))->lockForUpdate()->get();
            foreach ($reservations as $reservation) {
                $remaining = BigDecimal::of((string) $reservation->reserved_quantity)->minus((string) $reservation->issued_quantity)->minus((string) $reservation->released_quantity);
                $reservation->forceFill(['released_quantity' => (string) BigDecimal::of((string) $reservation->released_quantity)->plus($remaining)->toScale(4, RoundingMode::HalfUp), 'status' => InventoryReservationStatus::Cancelled, 'updated_by' => $actor->id])->save();
            }

            $requisition->forceFill(['status' => MaterialRequisitionStatus::Cancelled, 'decision_reason' => $reason, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('inventory.requisition.cancelled', $requisition, $actor, ['status' => $oldStatus], ['status' => MaterialRequisitionStatus::Cancelled->value], $reason);

            return $requisition->refresh();
        });
    }
}
