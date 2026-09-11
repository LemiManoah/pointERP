<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\WorkItemTemplate;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkItemTemplateDownloadController
{
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('viewAny', WorkItemTemplate::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['category', 'code', 'activity_name', 'activity_unit', 'default_selling_rate', 'specifications', 'resource_type', 'resource_name', 'resource_unit', 'quantity_per_unit', 'unit_cost', 'notes'], escape: '\\');
            fputcsv($handle, ['Quarry Operations', 'QRY-BLAST-01', 'Rock drilling and blasting', 'm3', '48000', 'Approved production blast pattern', 'equipment', 'Crawler hydraulic drill rig', 'hr', '0.045', '250000', 'Includes operator'], escape: '\\');
            fputcsv($handle, ['Quarry Operations', 'QRY-BLAST-01', 'Rock drilling and blasting', 'm3', '48000', 'Approved production blast pattern', 'material', 'ANFO bulk explosives', 'kg', '0.750', '12000', 'Powder factor per cubic metre'], escape: '\\');
            fputcsv($handle, ['Quarry Operations', 'QRY-CRUSH-01', 'Primary and secondary crushing', 't', '24000', 'Crush blasted granite to approved grading', 'equipment', 'Crushing and screening plant', 'hr', '0.010', '450000', 'Includes plant operator'], escape: '\\');

            fclose($handle);
        }, 'work-activity-templates-sample.csv', ['Content-Type' => 'text/csv']);
    }
}
