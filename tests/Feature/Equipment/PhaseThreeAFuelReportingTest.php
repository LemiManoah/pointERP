<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('shows the scoped fuel portfolio and exception summary to fleet managers', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();

    $this->actingAs($fleetManager)
        ->get(route('equipment.index', ['tab' => 'fuel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/equipment/index')
            ->where('activeTab', 'fuel')
            ->where('can.viewFuelDashboard', true)
            ->where('can.exportFuel', true)
            ->where('fuelSummary.review_required', 2)
            ->has('fuelTransactions', 4));
});

it('omits fuel costs and dashboard authority for site managers', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('equipment.index', ['tab' => 'fuel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('can.viewCosts', false)
            ->where('can.viewFuelDashboard', false)
            ->where('can.exportFuel', false)
            ->where('fuelTransactions.0.total_cost', null)
            ->where('fuelTransactions.0.currency_code', null));
});

it('exports the filtered scoped fuel ledger for authorised users', function (): void {
    $fleetManager = User::query()->where('email', 'fleet@point.test')->firstOrFail();

    $this->actingAs($fleetManager)
        ->get(route('equipment-fuel.export', [
            'exception_status' => 'review_required',
            'search' => 'EQ-RLR-002',
        ]))
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/csv')
        ->assertDownload();
});

it('forbids fuel exports without export authority', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)
        ->get(route('equipment-fuel.export'))
        ->assertForbidden();
});
