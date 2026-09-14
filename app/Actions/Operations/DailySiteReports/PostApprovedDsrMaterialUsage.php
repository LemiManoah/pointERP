<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Enums\DsrMaterialSource;
use App\Enums\DsrMaterialUsageStatus;
use App\Enums\InventoryMovementType;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportMaterialLine;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class PostApprovedDsrMaterialUsage
{
    public function __construct(private PostInventoryStockMovement $postMovement)
    {
        //
    }

    public function handle(DailySiteReport $report, User $actor): void
    {
        $report->loadMissing('materialLines');

        foreach ($report->materialLines as $line) {
            if ($line->material_usage_status !== DsrMaterialUsageStatus::Pending) {
                continue;
            }

            if ($line->material_source === DsrMaterialSource::External) {
                $line->forceFill([
                    'material_usage_status' => DsrMaterialUsageStatus::External,
                    'posted_by' => $actor->id,
                    'posted_at' => now(),
                ])->save();

                continue;
            }

            $this->postSiteStoreUsage($report, $line, $actor);
        }
    }

    private function postSiteStoreUsage(DailySiteReport $report, DailySiteReportMaterialLine $line, User $actor): void
    {
        $item = $line->item;
        $store = $line->store;

        if (! $item instanceof InventoryItem || ! $store instanceof InventoryStore || $line->unit_of_measure_id === null || $line->quantity === null) {
            throw ValidationException::withMessages([
                'material_lines' => $line->material_name.' needs an inventory item, site store, unit and quantity before this report can be approved.',
            ]);
        }

        if ($store->site_id !== $report->site_id) {
            throw ValidationException::withMessages([
                'material_lines' => $line->material_name.' must use a store belonging to this report site.',
            ]);
        }

        try {
            $movement = $this->postMovement->handle($store, $item, [
                'movement_type' => InventoryMovementType::Issue->value,
                'original_quantity' => $line->quantity,
                'original_unit_id' => $line->unit_of_measure_id,
                'conversion_multiplier' => $line->conversion_multiplier,
                'inventory_batch_id' => $line->inventory_batch_id,
                'source_type' => DailySiteReportMaterialLine::class,
                'source_id' => $line->id,
                'source_key' => 'dsr-material-usage:'.$line->id,
                'project_id' => $report->project_id,
                'site_id' => $report->site_id,
                'reason' => 'Material used in approved DSR '.$report->reference.'.',
            ], $actor);
        } catch (ValidationException $exception) {
            $detail = collect($exception->errors())->flatten()->first();

            throw ValidationException::withMessages([
                'material_lines' => sprintf(
                    '%s cannot be issued from %s: %s Add or transfer stock to the site store, select an available batch where required, or mark the material as externally supplied.',
                    $line->material_name,
                    $store->name,
                    is_string($detail) ? $detail : 'the stock entry is incomplete.',
                ),
            ]);
        }

        $line->forceFill([
            'material_usage_status' => DsrMaterialUsageStatus::Posted,
            'inventory_stock_movement_id' => $movement->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
        ])->save();
    }
}
