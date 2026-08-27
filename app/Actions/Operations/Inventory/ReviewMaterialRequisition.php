<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\InventoryReservationStatus;
use App\Enums\MaterialRequisitionStatus;
use App\Models\InventoryReservation;
use App\Models\InventoryStoreItem;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use App\Services\AuditLogger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReviewMaterialRequisition
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(MaterialRequisition $requisition, array $data, User $actor): MaterialRequisition
    {
        return DB::transaction(function () use ($actor, $data, $requisition): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            if ($requisition->status !== MaterialRequisitionStatus::Submitted) {
                throw ValidationException::withMessages(['requisition' => 'Only a submitted requisition can be reviewed.']);
            }

            $decision = (string) $data['decision'];
            if ($decision === 'approve') {
                $this->approve($requisition, $data, $actor);
            } else {
                $status = $decision === 'return' ? MaterialRequisitionStatus::Returned : MaterialRequisitionStatus::Rejected;
                $requisition->forceFill(['status' => $status, 'decision_reason' => $data['reason'], 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_by' => $actor->id])->save();
            }

            $this->auditLogger->record('inventory.requisition.'.$decision, $requisition, $actor, ['status' => MaterialRequisitionStatus::Submitted->value], ['status' => $requisition->status->value], isset($data['reason']) ? (string) $data['reason'] : null);

            return $requisition->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function approve(MaterialRequisition $requisition, array $data, User $actor): void
    {
        /** @var array<string, string> $approvals */
        $approvals = [];
        $approvalLines = $data['lines'] ?? [];
        if (is_array($approvalLines)) {
            foreach ($approvalLines as $approvalLine) {
                if (is_array($approvalLine) && isset($approvalLine['id'], $approvalLine['approved_quantity'])) {
                    $approvals[(string) $approvalLine['id']] = (string) $approvalLine['approved_quantity'];
                }
            }
        }

        $lines = $requisition->lines()->lockForUpdate()->get();
        foreach ($lines as $line) {
            $approved = BigDecimal::of($approvals[$line->id] ?? (string) $line->stock_quantity);
            if ($approved->isNegative() || $approved->isGreaterThan((string) $line->stock_quantity)) {
                throw ValidationException::withMessages(['lines' => 'Approved quantities must be between zero and the requested stock quantity.']);
            }

            $line->forceFill(['approved_quantity' => (string) $approved->toScale(4, RoundingMode::HalfUp)])->save();
            if ($approved->isZero()) {
                continue;
            }

            $storeItem = InventoryStoreItem::query()->where('inventory_store_id', $requisition->inventory_store_id)->where('inventory_item_id', $line->inventory_item_id)->where('is_active', true)->lockForUpdate()->first();
            if (! $storeItem instanceof InventoryStoreItem) {
                throw ValidationException::withMessages(['lines' => $line->item_name_snapshot.' is not enabled in the selected source store.']);
            }

            InventoryReservation::query()->updateOrCreate(
                ['tenant_id' => $requisition->tenant_id, 'source_type' => MaterialRequisitionLine::class, 'source_id' => $line->id, 'inventory_item_id' => $line->inventory_item_id],
                ['branch_id' => $requisition->branch_id, 'inventory_store_id' => $requisition->inventory_store_id, 'reserved_quantity' => (string) $approved->toScale(4), 'issued_quantity' => '0.0000', 'released_quantity' => '0.0000', 'status' => InventoryReservationStatus::Active, 'created_by' => $actor->id, 'updated_by' => $actor->id],
            );
        }

        $requisition->forceFill(['status' => MaterialRequisitionStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now(), 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision_reason' => $data['reason'] ?? null, 'updated_by' => $actor->id])->save();
    }
}
