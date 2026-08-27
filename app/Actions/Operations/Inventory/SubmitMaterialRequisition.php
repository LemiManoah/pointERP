<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\MaterialRequisitionStatus;
use App\Models\MaterialRequisition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SubmitMaterialRequisition
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(MaterialRequisition $requisition, User $actor): MaterialRequisition
    {
        return DB::transaction(function () use ($actor, $requisition): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            if (! $requisition->isEditable() || ! $requisition->lines()->exists()) {
                throw ValidationException::withMessages(['requisition' => 'Only a draft or returned requisition with at least one line can be submitted.']);
            }

            $oldStatus = $requisition->status->value;
            $requisition->forceFill(['status' => MaterialRequisitionStatus::Submitted, 'submitted_by' => $actor->id, 'submitted_at' => now(), 'decision_reason' => null, 'updated_by' => $actor->id])->save();
            $this->auditLogger->record('inventory.requisition.submitted', $requisition, $actor, ['status' => $oldStatus], ['status' => MaterialRequisitionStatus::Submitted->value]);

            return $requisition->refresh();
        });
    }
}
