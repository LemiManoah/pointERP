<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $tenantId = $user->tenant_id;

        return Inertia::render('dashboard', [
            'metrics' => [
                'projects' => Project::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'sites' => Site::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'documents' => Document::query()->where('tenant_id', $tenantId)->where('status', '!=', Document::STATUS_ARCHIVED)->count(),
                'expiringDocuments' => Document::query()->where('tenant_id', $tenantId)->expiringSoon()->count(),
            ],
            'dailyReports' => [
                'draft' => DailySiteReport::query()->where('tenant_id', $tenantId)->where('status', DailySiteReport::STATUS_DRAFT)->count(),
                'pending' => DailySiteReport::query()->where('tenant_id', $tenantId)->whereIn('status', [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED])->count(),
                'returned' => DailySiteReport::query()->where('tenant_id', $tenantId)->where('status', DailySiteReport::STATUS_RETURNED)->count(),
                'missing' => DailySiteReport::query()->where('tenant_id', $tenantId)->where('status', DailySiteReport::STATUS_MISSING)->count(),
                'approved' => DailySiteReport::query()->where('tenant_id', $tenantId)->where('status', DailySiteReport::STATUS_APPROVED)->count(),
                'outputValue' => DailySiteReport::query()->where('tenant_id', $tenantId)->sum('output_value'),
                'inputCost' => DailySiteReport::query()->where('tenant_id', $tenantId)->sum('input_cost'),
                'profitLoss' => DailySiteReport::query()->where('tenant_id', $tenantId)->sum('profit_loss'),
            ],
            'equipment' => [
                'total' => Equipment::query()->where('tenant_id', $tenantId)->count(),
                'available' => Equipment::query()->where('tenant_id', $tenantId)->where('current_status', 'available')->count(),
                'assigned' => Equipment::query()->where('tenant_id', $tenantId)->where('current_status', 'assigned')->count(),
                'underMaintenance' => Equipment::query()->where('tenant_id', $tenantId)->where('current_status', 'under_maintenance')->count(),
                'idle' => Equipment::query()->where('tenant_id', $tenantId)->where('current_status', 'idle')->count(),
                'outOfService' => Equipment::query()->where('tenant_id', $tenantId)->where('current_status', 'out_of_service')->count(),
                'retired' => Equipment::query()->where('tenant_id', $tenantId)->where('current_status', 'retired')->count(),
            ],
            'expiringDocuments' => Document::query()
                ->where('tenant_id', $tenantId)
                ->expiringSoon()
                ->orderBy('expires_on')
                ->limit(5)
                ->get()
                ->map(fn (Document $document): array => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'reference' => $document->reference,
                    'type_name' => $document->type?->name,
                    'expires_on' => $document->expires_on?->toDateString(),
                    'days_left' => $document->expires_on instanceof CarbonInterface
                        ? (int) today()->diffInDays($document->expires_on->startOfDay(), false)
                        : null,
                ]),
            'currentTenant' => $user->tenant->only([
                'id',
                'name',
                'code',
                'default_currency_code',
                'is_multibranch',
                'multi_currency_enabled',
                'timezone',
                'status',
            ]),
            'currentUser' => ['name' => $user->name],
        ]);
    }
}
