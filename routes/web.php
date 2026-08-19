<?php

declare(strict_types=1);

use App\Http\Controllers\AccessControl\RoleController as AccessRoleController;
use App\Http\Controllers\AccessControl\UserController as AccessUserController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Branches\CurrentBranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Foundation\BranchCurrencyController;
use App\Http\Controllers\Foundation\CurrencyController;
use App\Http\Controllers\Foundation\CurrencySettingController;
use App\Http\Controllers\Foundation\ExchangeRateApprovalController;
use App\Http\Controllers\Foundation\ExchangeRateController;
use App\Http\Controllers\Foundation\TenantCurrencyController;
use App\Http\Controllers\Foundation\TenantMultiCurrencyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\NotificationReadAllController;
use App\Http\Controllers\NotificationReadController;
use App\Http\Controllers\Operations\ContractController;
use App\Http\Controllers\Operations\CustomerController;
use App\Http\Controllers\Operations\DailySiteReportApprovalController;
use App\Http\Controllers\Operations\DailySiteReportController;
use App\Http\Controllers\Operations\DailySiteReportCorrectionApprovalController;
use App\Http\Controllers\Operations\DailySiteReportCorrectionController;
use App\Http\Controllers\Operations\DailySiteReportCorrectionRejectionController;
use App\Http\Controllers\Operations\DailySiteReportReturnController;
use App\Http\Controllers\Operations\DailySiteReportSubmitController;
use App\Http\Controllers\Operations\DocumentController;
use App\Http\Controllers\Operations\DocumentDownloadController;
use App\Http\Controllers\Operations\DocumentLinkController;
use App\Http\Controllers\Operations\DocumentTypeController;
use App\Http\Controllers\Operations\DocumentVersionController;
use App\Http\Controllers\Operations\DsrExceptionExportController;
use App\Http\Controllers\Operations\EquipmentAssignmentController;
use App\Http\Controllers\Operations\EquipmentAssignmentReturnController;
use App\Http\Controllers\Operations\EquipmentCategoryController;
use App\Http\Controllers\Operations\EquipmentController;
use App\Http\Controllers\Operations\EquipmentFuelExportController;
use App\Http\Controllers\Operations\EquipmentFuelTransactionApprovalController;
use App\Http\Controllers\Operations\EquipmentFuelTransactionController;
use App\Http\Controllers\Operations\EquipmentFuelTransactionReversalController;
use App\Http\Controllers\Operations\EquipmentLocationConfirmationController;
use App\Http\Controllers\Operations\EquipmentLocationController;
use App\Http\Controllers\Operations\EquipmentMaintenanceExportController;
use App\Http\Controllers\Operations\EquipmentMaintenanceScheduleController;
use App\Http\Controllers\Operations\EquipmentMaintenanceWorkOrderApprovalController;
use App\Http\Controllers\Operations\EquipmentMaintenanceWorkOrderCancellationController;
use App\Http\Controllers\Operations\EquipmentMaintenanceWorkOrderCompletionController;
use App\Http\Controllers\Operations\EquipmentMaintenanceWorkOrderController;
use App\Http\Controllers\Operations\EquipmentMaintenanceWorkOrderStartController;
use App\Http\Controllers\Operations\EquipmentMeterCorrectionApprovalController;
use App\Http\Controllers\Operations\EquipmentMeterCorrectionController;
use App\Http\Controllers\Operations\EquipmentMeterCorrectionRejectionController;
use App\Http\Controllers\Operations\EquipmentMeterReadingController;
use App\Http\Controllers\Operations\EquipmentTransferApprovalController;
use App\Http\Controllers\Operations\EquipmentTransferController;
use App\Http\Controllers\Operations\EquipmentTransferDispatchController;
use App\Http\Controllers\Operations\EquipmentTransferReceiptController;
use App\Http\Controllers\Operations\ExpectedDailySiteReportExcuseController;
use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\ProjectActivityController;
use App\Http\Controllers\Operations\ProjectController;
use App\Http\Controllers\Operations\ProjectUserController;
use App\Http\Controllers\Operations\ReportingCalendarController;
use App\Http\Controllers\Operations\ReportingCalendarExceptionController;
use App\Http\Controllers\Operations\SiteController;
use App\Http\Controllers\Operations\SiteUserController;
use App\Http\Controllers\Resources\StaffController;
use App\Http\Controllers\Resources\StaffPositionController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (): RedirectResponse => auth()->check()
    ? to_route('dashboard')
    : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('operations-dashboard', OperationsDashboardController::class)->name('operations-dashboard.index');
    Route::get('operations-dashboard/export', DsrExceptionExportController::class)->name('operations-dashboard.export');
    Route::resource('reporting-calendars', ReportingCalendarController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('reporting-calendars/{reportingCalendar}/exceptions', [ReportingCalendarExceptionController::class, 'store'])->name('reporting-calendars.exceptions.store');
    Route::delete('reporting-calendars/{reportingCalendar}/exceptions/{exception}', [ReportingCalendarExceptionController::class, 'destroy'])->name('reporting-calendars.exceptions.destroy');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}', NotificationReadController::class)->name('notifications.read');
    Route::post('notifications/read-all', NotificationReadAllController::class)->name('notifications.read-all');
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    Route::post('expected-daily-site-reports/{expectedDailySiteReport}/excuse', ExpectedDailySiteReportExcuseController::class)->name('expected-daily-site-reports.excuse');
    Route::put('current-branch', [CurrentBranchController::class, 'update'])->name('current-branch.update');
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');

    Route::resource('currencies', CurrencyController::class)->except(['show'])->names('foundation.currencies');
    Route::get('currency-settings', [CurrencySettingController::class, 'index'])->name('foundation.currency-settings.index');
    Route::put('currency-settings/multi-currency', [TenantMultiCurrencyController::class, 'update'])->name('foundation.currency-settings.multi-currency.toggle');
    Route::put('currency-settings/tenant/{currency}', [TenantCurrencyController::class, 'update'])->name('foundation.currency-settings.tenant.toggle');
    Route::post('currency-settings/branches', [BranchCurrencyController::class, 'store'])->name('foundation.currency-settings.branches.store');
    Route::resource('exchange-rates', ExchangeRateController::class)->only(['index', 'store', 'update', 'destroy'])->names('foundation.exchange-rates');
    Route::post('exchange-rates/{exchangeRate}/approve', [ExchangeRateApprovalController::class, 'store'])->name('foundation.exchange-rates.approve');

    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('contracts', ContractController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('projects', ProjectController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('sites', SiteController::class)->only(['show', 'store', 'update', 'destroy']);
    Route::resource('project-activities', ProjectActivityController::class)->only(['store', 'update', 'destroy']);
    Route::resource('daily-site-reports', DailySiteReportController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('daily-site-reports/{dailySiteReport}/submit', DailySiteReportSubmitController::class)->name('daily-site-reports.submit');
    Route::post('daily-site-reports/{dailySiteReport}/approve', DailySiteReportApprovalController::class)->name('daily-site-reports.approve');
    Route::post('daily-site-reports/{dailySiteReport}/return', DailySiteReportReturnController::class)->name('daily-site-reports.return');
    Route::post('daily-site-reports/{dailySiteReport}/corrections', [DailySiteReportCorrectionController::class, 'store'])->name('daily-site-reports.corrections.store');
    Route::post('daily-site-reports/{dailySiteReport}/corrections/{correction}/approve', DailySiteReportCorrectionApprovalController::class)->name('daily-site-reports.corrections.approve');
    Route::post('daily-site-reports/{dailySiteReport}/corrections/{correction}/reject', DailySiteReportCorrectionRejectionController::class)->name('daily-site-reports.corrections.reject');
    Route::resource('documents', DocumentController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');
    Route::get('documents/{document}/versions/{documentVersion}/download', DocumentDownloadController::class)->name('documents.versions.download');
    Route::post('documents/{document}/links', [DocumentLinkController::class, 'store'])->name('documents.links.store');
    Route::delete('documents/{document}/links/{documentLink}', [DocumentLinkController::class, 'destroy'])->name('documents.links.destroy');
    Route::resource('document-types', DocumentTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('equipment', EquipmentController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('equipment-fuel/export', EquipmentFuelExportController::class)->name('equipment-fuel.export');
    Route::get('equipment-maintenance/export', EquipmentMaintenanceExportController::class)->name('equipment-maintenance.export');
    Route::resource('equipment-categories', EquipmentCategoryController::class)->only(['store', 'update', 'destroy']);
    Route::resource('equipment-locations', EquipmentLocationController::class)->only(['store', 'update', 'destroy']);
    Route::post('equipment/{equipment}/meter-readings', [EquipmentMeterReadingController::class, 'store'])->name('equipment.meter-readings.store');
    Route::post('equipment/{equipment}/assignments', [EquipmentAssignmentController::class, 'store'])->name('equipment.assignments.store');
    Route::post('equipment/{equipment}/maintenance-schedules', [EquipmentMaintenanceScheduleController::class, 'store'])->name('equipment.maintenance-schedules.store');
    Route::put('equipment/{equipment}/maintenance-schedules/{equipmentMaintenanceSchedule}', [EquipmentMaintenanceScheduleController::class, 'update'])->name('equipment.maintenance-schedules.update');
    Route::post('equipment/{equipment}/maintenance-work-orders', [EquipmentMaintenanceWorkOrderController::class, 'store'])->name('equipment.maintenance-work-orders.store');
    Route::post('equipment-maintenance-work-orders/{equipmentMaintenanceWorkOrder}/approve', EquipmentMaintenanceWorkOrderApprovalController::class)->name('equipment-maintenance-work-orders.approve');
    Route::post('equipment-maintenance-work-orders/{equipmentMaintenanceWorkOrder}/start', EquipmentMaintenanceWorkOrderStartController::class)->name('equipment-maintenance-work-orders.start');
    Route::post('equipment-maintenance-work-orders/{equipmentMaintenanceWorkOrder}/complete', EquipmentMaintenanceWorkOrderCompletionController::class)->name('equipment-maintenance-work-orders.complete');
    Route::post('equipment-maintenance-work-orders/{equipmentMaintenanceWorkOrder}/cancel', EquipmentMaintenanceWorkOrderCancellationController::class)->name('equipment-maintenance-work-orders.cancel');
    Route::post('equipment-assignments/{equipmentAssignment}/return', EquipmentAssignmentReturnController::class)->name('equipment-assignments.return');
    Route::post('equipment/{equipment}/transfers', [EquipmentTransferController::class, 'store'])->name('equipment.transfers.store');
    Route::post('equipment-transfers/{equipmentTransfer}/approve', EquipmentTransferApprovalController::class)->name('equipment-transfers.approve');
    Route::post('equipment-transfers/{equipmentTransfer}/dispatch', EquipmentTransferDispatchController::class)->name('equipment-transfers.dispatch');
    Route::post('equipment-transfers/{equipmentTransfer}/receive', EquipmentTransferReceiptController::class)->name('equipment-transfers.receive');
    Route::post('equipment/{equipment}/location-confirmations', [EquipmentLocationConfirmationController::class, 'store'])->name('equipment.location-confirmations.store');
    Route::post('equipment/{equipment}/fuel-transactions', [EquipmentFuelTransactionController::class, 'store'])->name('equipment.fuel-transactions.store');
    Route::post('equipment-fuel-transactions/{equipmentFuelTransaction}/approve', EquipmentFuelTransactionApprovalController::class)->name('equipment-fuel-transactions.approve');
    Route::post('equipment-fuel-transactions/{equipmentFuelTransaction}/reverse', EquipmentFuelTransactionReversalController::class)->name('equipment-fuel-transactions.reverse');
    Route::post('equipment-meter-readings/{equipmentMeterReading}/corrections', [EquipmentMeterCorrectionController::class, 'store'])->name('equipment-meter-readings.corrections.store');
    Route::post('equipment-meter-readings/{equipmentMeterReading}/approve', EquipmentMeterCorrectionApprovalController::class)->name('equipment-meter-readings.approve');
    Route::post('equipment-meter-readings/{equipmentMeterReading}/reject', EquipmentMeterCorrectionRejectionController::class)->name('equipment-meter-readings.reject');
    Route::post('projects/{project}/users', [ProjectUserController::class, 'store'])->name('projects.users.store');
    Route::post('sites/{site}/users', [SiteUserController::class, 'store'])->name('sites.users.store');

    Route::redirect('access-control', '/users')->name('access-control.index');
    Route::resource('users', AccessUserController::class)->only(['index', 'store', 'update', 'destroy'])->names('access-control.users');
    Route::resource('roles', AccessRoleController::class)->only(['index', 'store', 'update', 'destroy'])->names('access-control.roles');

    Route::resource('staff', StaffController::class)->only(['index', 'store', 'update', 'destroy'])->names('resources.staff');
    Route::resource('staff-positions', StaffPositionController::class)->only(['index', 'store', 'update', 'destroy'])->names('resources.staff-positions');
});

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware('guest')->group(function (): void {
    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
