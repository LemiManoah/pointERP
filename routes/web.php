<?php

declare(strict_types=1);

use App\Http\Controllers\AccessControl\RoleController as AccessRoleController;
use App\Http\Controllers\AccessControl\UserController as AccessUserController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Branches\CurrentBranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Foundation\BranchCurrencyController;
use App\Http\Controllers\Foundation\CountryController;
use App\Http\Controllers\Foundation\CurrencyController;
use App\Http\Controllers\Foundation\CurrencySettingController;
use App\Http\Controllers\Foundation\ExchangeRateApprovalController;
use App\Http\Controllers\Foundation\ExchangeRateController;
use App\Http\Controllers\Foundation\TenantCurrencyController;
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
    Route::put('current-branch', [CurrentBranchController::class, 'update'])->name('current-branch.update');
    Route::get('audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail.index');

    Route::resource('countries', CountryController::class)->except(['show'])->names('foundation.countries');
    Route::resource('currencies', CurrencyController::class)->except(['show'])->names('foundation.currencies');
    Route::get('currency-settings', [CurrencySettingController::class, 'index'])->name('foundation.currency-settings.index');
    Route::put('currency-settings/tenant/{currency}', [TenantCurrencyController::class, 'update'])->name('foundation.currency-settings.tenant.toggle');
    Route::post('currency-settings/branches', [BranchCurrencyController::class, 'store'])->name('foundation.currency-settings.branches.store');
    Route::resource('exchange-rates', ExchangeRateController::class)->only(['index', 'store', 'update', 'destroy'])->names('foundation.exchange-rates');
    Route::post('exchange-rates/{exchangeRate}/approve', [ExchangeRateApprovalController::class, 'store'])->name('foundation.exchange-rates.approve');

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
