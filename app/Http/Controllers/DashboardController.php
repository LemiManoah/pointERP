<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Contract;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
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
                'tenants' => Tenant::query()->count(),
                'countries' => Country::query()->active()->count(),
                'currencies' => Currency::query()->active()->count(),
                'branches' => Branch::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'users' => User::query()->where('tenant_id', $tenantId)->where('is_active', true)->where('is_support', false)->count(),
                'roles' => Role::query()->count(),
                'customers' => Customer::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'contracts' => Contract::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'projects' => Project::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'sites' => Site::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'documents' => Document::query()->where('tenant_id', $tenantId)->where('status', '!=', Document::STATUS_ARCHIVED)->count(),
                'expiringDocuments' => Document::query()->where('tenant_id', $tenantId)->expiringSoon()->count(),
                'phase' => 'Phase 2C',
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
            'currentTenant' => $user->tenant?->only([
                'id',
                'name',
                'code',
                'default_currency_code',
                'is_multibranch',
                'multi_currency_enabled',
                'timezone',
                'status',
            ]),
        ]);
    }
}
