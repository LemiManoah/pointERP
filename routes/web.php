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
use App\Http\Controllers\Operations\DsrExpenseController;
use App\Http\Controllers\Operations\DsrMaterialAllocationController;
use App\Http\Controllers\Operations\DsrMaterialDirectIssueController;
use App\Http\Controllers\Operations\DsrMaterialExternalController;
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
use App\Http\Controllers\Operations\ExpenseApprovalController;
use App\Http\Controllers\Operations\ExpenseCancellationController;
use App\Http\Controllers\Operations\ExpenseCategoryController;
use App\Http\Controllers\Operations\ExpenseCategoryPurgeController;
use App\Http\Controllers\Operations\ExpenseController;
use App\Http\Controllers\Operations\ExpenseExportController;
use App\Http\Controllers\Operations\ExpenseItemController;
use App\Http\Controllers\Operations\ExpenseItemPurgeController;
use App\Http\Controllers\Operations\ExpensePaymentController;
use App\Http\Controllers\Operations\ExpensePaymentReversalController;
use App\Http\Controllers\Operations\ExpensePdfController;
use App\Http\Controllers\Operations\ExpenseRejectionController;
use App\Http\Controllers\Operations\ExpenseSubmitController;
use App\Http\Controllers\Operations\InventoryCategoryController;
use App\Http\Controllers\Operations\InventoryCategoryPermanentDeleteController;
use App\Http\Controllers\Operations\InventoryController;
use App\Http\Controllers\Operations\InventoryDirectReceiptController;
use App\Http\Controllers\Operations\InventoryGoodsReceiptController;
use App\Http\Controllers\Operations\InventoryItemController;
use App\Http\Controllers\Operations\InventoryItemPermanentDeleteController;
use App\Http\Controllers\Operations\InventoryItemPriceController;
use App\Http\Controllers\Operations\InventoryOperationsDashboardController;
use App\Http\Controllers\Operations\InventoryPriceTierController;
use App\Http\Controllers\Operations\InventoryPriceTierPermanentDeleteController;
use App\Http\Controllers\Operations\InventoryReconciliationApprovalController;
use App\Http\Controllers\Operations\InventoryReconciliationRejectionController;
use App\Http\Controllers\Operations\InventoryReportExportController;
use App\Http\Controllers\Operations\InventoryReportPdfController;
use App\Http\Controllers\Operations\InventoryStockController;
use App\Http\Controllers\Operations\InventoryStockCountController;
use App\Http\Controllers\Operations\InventoryStockExportController;
use App\Http\Controllers\Operations\InventoryStockMovementController;
use App\Http\Controllers\Operations\InventoryStockMovementReversalController;
use App\Http\Controllers\Operations\InventoryStoreController;
use App\Http\Controllers\Operations\InventoryStoreItemController;
use App\Http\Controllers\Operations\InventoryStorePermanentDeleteController;
use App\Http\Controllers\Operations\InventoryTransferApprovalController;
use App\Http\Controllers\Operations\InventoryTransferController;
use App\Http\Controllers\Operations\InventoryTransferRejectionController;
use App\Http\Controllers\Operations\InventoryUnitConversionController;
use App\Http\Controllers\Operations\InventoryUnitConversionPermanentDeleteController;
use App\Http\Controllers\Operations\MaterialRequisitionController;
use App\Http\Controllers\Operations\MaterialRequisitionIssueController;
use App\Http\Controllers\Operations\MaterialRequisitionReturnController;
use App\Http\Controllers\Operations\MaterialRequisitionReviewController;
use App\Http\Controllers\Operations\MaterialRequisitionSubmissionController;
use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\PosPaymentController;
use App\Http\Controllers\Operations\PosSaleController;
use App\Http\Controllers\Operations\ProjectActivityController;
use App\Http\Controllers\Operations\ProjectController;
use App\Http\Controllers\Operations\ProjectEstimateApprovalController;
use App\Http\Controllers\Operations\ProjectEstimateController;
use App\Http\Controllers\Operations\ProjectUserController;
use App\Http\Controllers\Operations\PurchaseOrderCloseController;
use App\Http\Controllers\Operations\PurchaseOrderController;
use App\Http\Controllers\Operations\PurchaseOrderReviewController;
use App\Http\Controllers\Operations\PurchaseOrderSubmissionController;
use App\Http\Controllers\Operations\ReportingCalendarController;
use App\Http\Controllers\Operations\ReportingCalendarExceptionController;
use App\Http\Controllers\Operations\SiteController;
use App\Http\Controllers\Operations\SiteUserController;
use App\Http\Controllers\Operations\UnitOfMeasureController;
use App\Http\Controllers\Operations\UnitOfMeasurePermanentDeleteController;
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
    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('expenses-export.csv', ExpenseExportController::class)->name('expenses.export');
    Route::get('expenses-export.pdf', ExpensePdfController::class)->name('expenses.export.pdf');
    Route::resource('expense-categories', ExpenseCategoryController::class)->only(['store', 'update', 'destroy']);
    Route::resource('expense-items', ExpenseItemController::class)->only(['store', 'update', 'destroy']);
    Route::delete('expense-categories/{expenseCategory}/permanently', ExpenseCategoryPurgeController::class)->name('expense-categories.purge');
    Route::delete('expense-items/{expenseItem}/permanently', ExpenseItemPurgeController::class)->name('expense-items.purge');
    Route::post('expenses/{expense}/submit', ExpenseSubmitController::class)->name('expenses.submit');
    Route::post('expenses/{expense}/approve', ExpenseApprovalController::class)->name('expenses.approve');
    Route::post('expenses/{expense}/reject', ExpenseRejectionController::class)->name('expenses.reject');
    Route::post('expenses/{expense}/cancel', ExpenseCancellationController::class)->name('expenses.cancel');
    Route::post('expenses/{expense}/payments', ExpensePaymentController::class)->name('expenses.payments.store');
    Route::post('expense-payments/{expensePayment}/reverse', ExpensePaymentReversalController::class)->name('expense-payments.reverse');
    Route::post('daily-site-reports/{dailySiteReport}/expenses', DsrExpenseController::class)->name('daily-site-reports.expenses.store');
    Route::resource('contracts', ContractController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('projects', ProjectController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('projects/{project}/estimates/create', [ProjectEstimateController::class, 'create'])->name('project-estimates.create');
    Route::post('projects/{project}/estimates', [ProjectEstimateController::class, 'store'])->name('project-estimates.store');
    Route::get('estimates/{projectEstimate}', [ProjectEstimateController::class, 'show'])->name('project-estimates.show');
    Route::put('estimates/{projectEstimate}', [ProjectEstimateController::class, 'update'])->name('project-estimates.update');
    Route::delete('estimates/{projectEstimate}', [ProjectEstimateController::class, 'destroy'])->name('project-estimates.destroy');
    Route::post('estimates/{projectEstimate}/approve', ProjectEstimateApprovalController::class)->name('project-estimates.approve');
    Route::resource('sites', SiteController::class)->only(['show', 'store', 'update', 'destroy']);
    Route::resource('project-activities', ProjectActivityController::class)->only(['store', 'update', 'destroy']);
    Route::resource('daily-site-reports', DailySiteReportController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('daily-site-reports/{dailySiteReport}/submit', DailySiteReportSubmitController::class)->name('daily-site-reports.submit');
    Route::post('daily-site-reports/{dailySiteReport}/approve', DailySiteReportApprovalController::class)->name('daily-site-reports.approve');
    Route::post('dsr-material-lines/{dailySiteReportMaterialLine}/allocate', DsrMaterialAllocationController::class)->name('dsr-material-lines.allocate');
    Route::post('dsr-material-lines/{dailySiteReportMaterialLine}/direct-issue', DsrMaterialDirectIssueController::class)->name('dsr-material-lines.direct-issue');
    Route::post('dsr-material-lines/{dailySiteReportMaterialLine}/external', DsrMaterialExternalController::class)->name('dsr-material-lines.external');
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
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory-dashboard', InventoryOperationsDashboardController::class)->name('inventory.dashboard');
    Route::get('pos', [PosSaleController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosSaleController::class, 'store'])->name('pos.store');
    Route::get('pos/{posSale}', [PosSaleController::class, 'show'])->name('pos.show');
    Route::post('pos/{posSale}/payments', PosPaymentController::class)->name('pos.payments.store');
    Route::get('inventory/reports/{report}', InventoryReportExportController::class)->name('inventory.reports.export');
    Route::get('inventory/reports/{report}/pdf', InventoryReportPdfController::class)->name('inventory.reports.pdf');
    Route::get('inventory/stock', [InventoryStockController::class, 'index'])->name('inventory.stock.index');
    Route::get('inventory/stock/export', InventoryStockExportController::class)->name('inventory.stock.export');
    Route::get('inventory/receipts', [InventoryGoodsReceiptController::class, 'index'])->name('inventory.receipts.index');
    Route::post('inventory/receipts', [InventoryGoodsReceiptController::class, 'store'])->name('inventory.receipts.store');
    Route::get('inventory/receipts/{inventoryGoodsReceipt}', [InventoryGoodsReceiptController::class, 'show'])->name('inventory.receipts.show');
    Route::get('inventory/stock-movements', [InventoryStockMovementController::class, 'index'])->name('inventory.movements.index');
    Route::get('inventory/add-stock', [InventoryDirectReceiptController::class, 'create'])->name('inventory.direct-receipts.create');
    Route::post('inventory/add-stock', [InventoryDirectReceiptController::class, 'store'])->name('inventory.direct-receipts.store');
    Route::get('inventory/add-stock/{inventoryDirectReceipt}', [InventoryDirectReceiptController::class, 'show'])->name('inventory.direct-receipts.show');
    Route::get('inventory/transfers', [InventoryTransferController::class, 'index'])->name('inventory.transfers.index');
    Route::post('inventory/transfers', [InventoryTransferController::class, 'store'])->name('inventory.transfers.store');
    Route::post('inventory/transfers/{inventoryTransfer}/approve', InventoryTransferApprovalController::class)->name('inventory.transfers.approve');
    Route::post('inventory/transfers/{inventoryTransfer}/reject', InventoryTransferRejectionController::class)->name('inventory.transfers.reject');
    Route::get('inventory/reconciliations', [InventoryStockCountController::class, 'index'])->name('inventory.stock-counts.index');
    Route::post('inventory/reconciliations', [InventoryStockCountController::class, 'store'])->name('inventory.stock-counts.store');
    Route::post('inventory/reconciliations/{inventoryReconciliation}/approve', InventoryReconciliationApprovalController::class)->name('inventory.reconciliations.approve');
    Route::post('inventory/reconciliations/{inventoryReconciliation}/reject', InventoryReconciliationRejectionController::class)->name('inventory.reconciliations.reject');
    Route::get('inventory/requisitions', [MaterialRequisitionController::class, 'index'])->name('inventory.requisitions.index');
    Route::post('inventory/requisitions', [MaterialRequisitionController::class, 'store'])->name('inventory.requisitions.store');
    Route::get('inventory/requisitions/{materialRequisition}', [MaterialRequisitionController::class, 'show'])->name('inventory.requisitions.show');
    Route::put('inventory/requisitions/{materialRequisition}', [MaterialRequisitionController::class, 'update'])->name('inventory.requisitions.update');
    Route::delete('inventory/requisitions/{materialRequisition}', [MaterialRequisitionController::class, 'destroy'])->name('inventory.requisitions.destroy');
    Route::post('inventory/requisitions/{materialRequisition}/submit', MaterialRequisitionSubmissionController::class)->name('inventory.requisitions.submit');
    Route::post('inventory/requisitions/{materialRequisition}/review', MaterialRequisitionReviewController::class)->name('inventory.requisitions.review');
    Route::post('inventory/requisitions/{materialRequisition}/lines/{materialRequisitionLine}/issue', MaterialRequisitionIssueController::class)->name('inventory.requisitions.lines.issue');
    Route::post('inventory/requisitions/{materialRequisition}/lines/{materialRequisitionLine}/return', MaterialRequisitionReturnController::class)->name('inventory.requisitions.lines.return');
    Route::resource('inventory/purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'])->names('inventory.purchase-orders');
    Route::post('inventory/purchase-orders/{purchaseOrder}/submit', PurchaseOrderSubmissionController::class)->name('inventory.purchase-orders.submit');
    Route::post('inventory/purchase-orders/{purchaseOrder}/review', PurchaseOrderReviewController::class)->name('inventory.purchase-orders.review');
    Route::post('inventory/purchase-orders/{purchaseOrder}/close', PurchaseOrderCloseController::class)->name('inventory.purchase-orders.close');
    Route::post('inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
    Route::put('inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
    Route::delete('inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'destroy'])->name('inventory.categories.destroy');
    Route::delete('inventory/categories/{inventoryCategory}/permanent', InventoryCategoryPermanentDeleteController::class)->name('inventory.categories.force-destroy');
    Route::post('inventory/units', [UnitOfMeasureController::class, 'store'])->name('inventory.units.store');
    Route::put('inventory/units/{unitOfMeasure}', [UnitOfMeasureController::class, 'update'])->name('inventory.units.update');
    Route::delete('inventory/units/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy'])->name('inventory.units.destroy');
    Route::delete('inventory/units/{unitOfMeasure}/permanent', UnitOfMeasurePermanentDeleteController::class)->name('inventory.units.force-destroy');
    Route::post('inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
    Route::get('inventory/items/{inventoryItem}', [InventoryItemController::class, 'show'])->name('inventory.items.show');
    Route::put('inventory/items/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.items.update');
    Route::delete('inventory/items/{inventoryItem}', [InventoryItemController::class, 'destroy'])->name('inventory.items.destroy');
    Route::delete('inventory/items/{inventoryItem}/permanent', InventoryItemPermanentDeleteController::class)->name('inventory.items.force-destroy');
    Route::post('inventory/price-lists', [InventoryPriceTierController::class, 'store'])->name('inventory.price-lists.store');
    Route::put('inventory/price-lists/{inventoryPriceTier}', [InventoryPriceTierController::class, 'update'])->name('inventory.price-lists.update');
    Route::delete('inventory/price-lists/{inventoryPriceTier}', [InventoryPriceTierController::class, 'destroy'])->name('inventory.price-lists.destroy');
    Route::delete('inventory/price-lists/{inventoryPriceTier}/permanent', InventoryPriceTierPermanentDeleteController::class)->name('inventory.price-lists.force-destroy');
    Route::post('inventory/items/{inventoryItem}/conversions', [InventoryUnitConversionController::class, 'store'])->name('inventory.items.conversions.store');
    Route::put('inventory/items/{inventoryItem}/conversions/{inventoryUnitConversion}', [InventoryUnitConversionController::class, 'update'])->name('inventory.items.conversions.update');
    Route::delete('inventory/items/{inventoryItem}/conversions/{inventoryUnitConversion}', [InventoryUnitConversionController::class, 'destroy'])->name('inventory.items.conversions.destroy');
    Route::delete('inventory/items/{inventoryItem}/conversions/{inventoryUnitConversion}/permanent', InventoryUnitConversionPermanentDeleteController::class)->name('inventory.items.conversions.force-destroy');
    Route::post('inventory/items/{inventoryItem}/prices', [InventoryItemPriceController::class, 'store'])->name('inventory.items.prices.store');
    Route::put('inventory/items/{inventoryItem}/prices/{inventoryItemPrice}', [InventoryItemPriceController::class, 'update'])->name('inventory.items.prices.update');
    Route::post('inventory/items/{inventoryItem}/store-settings', [InventoryStoreItemController::class, 'store'])->name('inventory.items.store-settings.store');
    Route::put('inventory/items/{inventoryItem}/store-settings/{inventoryStoreItem}', [InventoryStoreItemController::class, 'update'])->name('inventory.items.store-settings.update');
    Route::post('inventory/stock-movements/{inventoryStockMovement}/reverse', InventoryStockMovementReversalController::class)->name('inventory.stock-movements.reverse');
    Route::post('inventory/stores', [InventoryStoreController::class, 'store'])->name('inventory.stores.store');
    Route::put('inventory/stores/{inventoryStore}', [InventoryStoreController::class, 'update'])->name('inventory.stores.update');
    Route::delete('inventory/stores/{inventoryStore}', [InventoryStoreController::class, 'destroy'])->name('inventory.stores.destroy');
    Route::delete('inventory/stores/{inventoryStore}/permanent', InventoryStorePermanentDeleteController::class)->name('inventory.stores.force-destroy');
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
