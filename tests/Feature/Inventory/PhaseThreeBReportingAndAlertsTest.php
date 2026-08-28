<?php

declare(strict_types=1);

use App\Actions\Operations\Inventory\ProcessInventoryAlerts;
use App\Models\User;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PointInvestmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PointInvestmentSeeder::class);
    resolve(TenantContext::class)->set(User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant);
});

it('opens inventory while an all-branches user has no current branch selected', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($director)
        ->withSession(['current_branch_all' => true])
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/inventory/index')
            ->where('priceCurrency', 'UGX'));
});

it('shows the authorised inventory operations dashboard with seeded pilot exceptions', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($director)->get(route('inventory.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/inventory/dashboard')
            ->where('canExport', true)
            ->where('canExportDsr', true)
            ->where('metrics.overdue_purchase_orders', 1)
            ->where('metrics.rejected_receipt_lines', 2)
            ->has('lowStock')
            ->has('unfulfilledRequisitions')
            ->has('unreconciledMaterials'));
});

it('keeps commercial cost columns out of quantity reports without cost authority', function (): void {
    $storeKeeper = User::query()->where('email', 'store.kla@point.test')->firstOrFail();

    $response = $this->actingAs($storeKeeper)->get(route('inventory.reports.export', 'purchase-orders'));

    $response->assertOk();
    expect($response->streamedContent())->not->toContain('Unit price')->not->toContain('Line amount');
});

it('rejects direct report exports without the export permission', function (): void {
    $siteManager = User::query()->where('email', 'engineer.gulu@point.test')->firstOrFail();

    $this->actingAs($siteManager)->get(route('inventory.reports.export', 'stock-balances'))->assertForbidden();
    $this->actingAs($siteManager)->get(route('inventory.reports.pdf', 'stock-balances'))->assertForbidden();
});

it('downloads a scoped inventory report as a PDF', function (): void {
    $director = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($director)->get(route('inventory.reports.pdf', 'stock-balances'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('deduplicates scheduled inventory alerts within the reminder interval', function (): void {
    $asOf = CarbonImmutable::now();
    $action = resolve(ProcessInventoryAlerts::class);
    $action->handle($asOf);

    $count = User::query()->get()->sum(fn (User $user): int => $user->notifications()->get()->filter(fn ($notification): bool => isset($notification->data['alert_key']))->count());

    $action->handle($asOf);
    $secondCount = User::query()->get()->sum(fn (User $user): int => $user->notifications()->get()->filter(fn ($notification): bool => isset($notification->data['alert_key']))->count());

    expect($count)->toBeGreaterThan(0)->and($secondCount)->toBe($count);
});
