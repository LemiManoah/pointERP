<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'countries' => Country::query()->active()->count(),
                'currencies' => Currency::query()->active()->count(),
                'phase' => 'Phase 1A',
            ],
            'currentTenant' => $request->user()?->tenant?->only([
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
