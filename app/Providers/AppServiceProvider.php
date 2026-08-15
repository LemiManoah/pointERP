<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\Contract;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\ExchangeRate;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ReportingCalendar;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffPosition;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Policies\BranchCurrencyPolicy;
use App\Policies\BranchPolicy;
use App\Policies\ContractPolicy;
use App\Policies\CountryPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DailySiteReportPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\DocumentTypePolicy;
use App\Policies\EquipmentCategoryPolicy;
use App\Policies\EquipmentLocationPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\ExchangeRatePolicy;
use App\Policies\ProjectActivityPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ReportingCalendarPolicy;
use App\Policies\RolePolicy;
use App\Policies\SitePolicy;
use App\Policies\StaffPolicy;
use App\Policies\StaffPositionPolicy;
use App\Policies\TenantCurrencyPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(BranchContext::class);
        $this->app->scoped(TenantScope::class);
    }

    public function boot(): void
    {
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(BranchCurrency::class, BranchCurrencyPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(DailySiteReport::class, DailySiteReportPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentType::class, DocumentTypePolicy::class);
        Gate::policy(Equipment::class, EquipmentPolicy::class);
        Gate::policy(EquipmentCategory::class, EquipmentCategoryPolicy::class);
        Gate::policy(EquipmentLocation::class, EquipmentLocationPolicy::class);
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectActivity::class, ProjectActivityPolicy::class);
        Gate::policy(ReportingCalendar::class, ReportingCalendarPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Staff::class, StaffPolicy::class);
        Gate::policy(StaffPosition::class, StaffPositionPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(TenantCurrency::class, TenantCurrencyPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
