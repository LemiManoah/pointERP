<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MaterialRequisitionIssueDocumentController
{
    public function __invoke(MaterialRequisition $materialRequisition, InventoryStockMovement $inventoryStockMovement, AuditLogger $auditLogger): StreamedResponse
    {
        Gate::authorize('view', $materialRequisition);

        $belongsToRequisition = $inventoryStockMovement->source_type === MaterialRequisitionLine::class
            && is_string($inventoryStockMovement->source_id)
            && MaterialRequisitionLine::query()
                ->whereKey($inventoryStockMovement->source_id)
                ->where('material_requisition_id', $materialRequisition->id)
                ->exists();
        abort_unless($belongsToRequisition, 404);

        $disk = $inventoryStockMovement->handover_document_disk;
        $path = $inventoryStockMovement->handover_document_path;
        $name = $inventoryStockMovement->handover_document_name;
        abort_unless(is_string($disk) && is_string($path) && is_string($name), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $auditLogger->record('inventory.requisition.handover_document_downloaded', $inventoryStockMovement, $actor, [], [
            'requisition_id' => $materialRequisition->id,
            'document_name' => $name,
        ], null, $materialRequisition->branch);

        return Storage::disk($disk)->download($path, $name);
    }
}
