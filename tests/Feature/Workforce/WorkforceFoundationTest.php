<?php

declare(strict_types=1);

use App\Enums\StaffDeploymentStatus;
use App\Actions\Operations\Projects\SaveProject;
use App\Actions\AccessControl\Users\CreateAccessUser;
use Illuminate\Validation\ValidationException;
use App\Models\AuditActivity;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffDeployment;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\TenantContext;
use Database\Seeders\QuarryDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkforceDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(QuarryDemoSeeder::class);
    $this->seed(WorkforceDemoSeeder::class);

    resolve(TenantContext::class)->set(
        User::query()->where('email', 'lemi@gmail.com')->firstOrFail()->tenant,
    );
});

it('registers a worker without email or position and keeps company staff separate', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
    $trade = WorkforceTrade::query()->where('is_active', true)->firstOrFail();
    $this->actingAs($administrator)->post(route('workforce.workers.store'), [
        'branch_id' => $project->branch_id, 'name' => 'New site worker',
        'employment_type' => 'casual', 'primary_trade_id' => $trade->id, 'status' => 'active',
        'person_category' => 'company_staff',
    ])->assertSessionHasNoErrors()->assertRedirect(route('workforce.workers.index'));

    $worker = Staff::query()->where('name', 'New site worker')->firstOrFail();
    expect($worker->person_category)->toBe('workforce')
        ->and($worker->email)->toBeNull()->and($worker->staff_position_id)->toBeNull()
        ->and($worker->user)->toBeNull();
    $this->get(route('workforce.workers.index'))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('directory', 'workforce')->where('staff.0.id', $worker->id));
    $this->get(route('resources.staff.index'))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('directory', 'company_staff')
            ->where('staff', fn ($rows): bool => ! collect($rows)->contains('id', $worker->id)));

    $this->post(route('workforce.deployments.store'), [
        'staff_id' => $worker->id, 'project_id' => $project->id, 'starts_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $first = StaffDeployment::query()->where('staff_id', $worker->id)->firstOrFail();
    $this->delete(route('workforce.deployments.destroy', $first))->assertSessionHasNoErrors();
    $this->post(route('workforce.deployments.store'), [
        'staff_id' => $worker->id, 'project_id' => $project->id, 'starts_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($first->fresh()->status)->toBe(StaffDeploymentStatus::Ended)
        ->and($worker->deployments()->count())->toBe(2)
        ->and($worker->fresh()->status)->toBe('active');

    expect(fn () => resolve(CreateAccessUser::class)->handle(['staff_id' => $worker->id, 'password' => 'example-password']))
        ->toThrow(ValidationException::class);
    $projectData = ['branch_id' => $project->branch_id, 'reference' => $project->reference,
        'name' => $project->name, 'base_currency_code' => $project->base_currency_code, 'status' => 'completed'];
    resolve(SaveProject::class)->handle($projectData, $administrator, $project);
    expect($worker->deployments()->where('status', 'active')->count())->toBe(0)
        ->and($worker->deployments()->count())->toBe(2)
        ->and($worker->fresh()->status)->toBe('active');
    resolve(SaveProject::class)->handle([...$projectData, 'status' => 'active'], $administrator, $project);
    expect($worker->deployments()->where('status', 'active')->count())->toBe(0);
});

it('retains company staff requirements and requires a worker trade', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
    $payload = ['branch_id' => $project->branch_id, 'name' => 'Incomplete person',
        'employment_type' => 'fixed_term', 'status' => 'active'];
    $this->actingAs($administrator)->post(route('resources.staff.store'), $payload)
        ->assertSessionHasErrors(['email', 'staff_position_id']);
    $this->post(route('workforce.workers.store'), $payload)->assertSessionHasErrors('primary_trade_id');
});

it('uses workforce permissions independently of company staff management', function (): void {
    $engineer = User::query()->where('email', 'luate@gmail.com')->firstOrFail();
    $engineer->syncRoles([]);
    $engineer->syncPermissions(['workforce.deployments.manage']);
    $this->actingAs($engineer)->get(route('workforce.workers.index'))->assertOk();
    $this->get(route('resources.staff.index'))->assertForbidden();
});

it('moves an existing person between directories without replacing their identity or deployments', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $person = Staff::query()->where('email', 'luate@gmail.com')->firstOrFail();
    $deploymentIds = $person->deployments()->pluck('id')->all();
    $payload = [...$person->only(['branch_id', 'staff_position_id', 'staff_number', 'name', 'email', 'phone', 'status']),
        'employment_type' => $person->employment_type->value,
        'primary_trade_id' => WorkforceTrade::query()->where('is_active', true)->firstOrFail()->id,
        'person_category' => 'workforce'];
    $this->actingAs($administrator)->put(route('resources.staff.update', $person), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route('workforce.workers.index'));
    expect($person->fresh()->person_category)->toBe('workforce')
        ->and($person->deployments()->pluck('id')->all())->toBe($deploymentIds);
    $this->put(route('workforce.workers.update', $person), [...$payload, 'person_category' => 'company_staff'])
        ->assertSessionHasNoErrors()->assertRedirect(route('resources.staff.index'));
    expect($person->fresh()->person_category)->toBe('company_staff')
        ->and($person->deployments()->pluck('id')->all())->toBe($deploymentIds);
});

it('shows the workforce foundation to an authorised administrator', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($administrator)
        ->get(route('workforce.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('operations/workforce/index')
            ->has('trades', 8)
            ->has('deployments', 3)
            ->where('can.manageTrades', true)
            ->where('can.manageDeployments', true));
});

it('keeps workforce permissions unassigned from operational roles by default', function (): void {
    $siteEngineer = User::query()->where('email', 'luate@gmail.com')->firstOrFail();

    $this->actingAs($siteEngineer)
        ->get(route('workforce.index'))
        ->assertForbidden();
});

it('creates an audited tenant trade with an autogenerated code', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();

    $this->actingAs($administrator)
        ->post(route('workforce.trades.store'), [
            'code' => '',
            'name' => 'Drill rig operator',
            'category' => 'skilled',
            'is_active' => true,
        ])
        ->assertRedirect(route('workforce.index', ['tab' => 'trades']));

    $trade = WorkforceTrade::query()->where('code', 'DRILL-RIG-OPERATOR')->firstOrFail();

    expect($trade->tenant_id)->toBe($administrator->tenant_id)
        ->and(AuditActivity::query()->where('event', 'workforce.trade.created')->where('subject_id', $trade->id)->exists())->toBeTrue();
});

it('ends the current deployment when staff is reassigned', function (): void {
    $administrator = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
    $staff = Staff::query()->where('email', 'luate@gmail.com')->firstOrFail();
    $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
    $site = Site::query()->where('project_id', $project->id)->where('reference', 'CRUSHER-YARD')->firstOrFail();
    $trade = WorkforceTrade::query()->where('code', 'SITE-ENGINEER')->firstOrFail();

    $this->actingAs($administrator)
        ->post(route('workforce.deployments.store'), [
            'staff_id' => $staff->id,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'workforce_trade_id' => $trade->id,
            'starts_on' => now()->addDay()->toDateString(),
            'notes' => 'Temporary crusher-yard coverage.',
        ])
        ->assertRedirect(route('workforce.index'));

    expect(StaffDeployment::query()->where('staff_id', $staff->id)->where('status', StaffDeploymentStatus::Ended->value)->count())->toBe(1)
        ->and(StaffDeployment::query()->where('staff_id', $staff->id)->where('status', StaffDeploymentStatus::Active->value)->count())->toBe(1)
        ->and(StaffDeployment::query()->where('staff_id', $staff->id)->where('status', StaffDeploymentStatus::Active->value)->value('site_id'))->toBe($site->id);
});
