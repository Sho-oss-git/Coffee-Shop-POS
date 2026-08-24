<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CookieController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\IncomeReportController;
use App\Http\Controllers\ClockStatusController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\ActionRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ============================================================
// PUBLIC ROUTES
// ============================================================

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// ============================================================
// AUTHENTICATED ROUTES
// ============================================================

Route::middleware(['auth', 'verified'])->group(function () {

    // ========================================================
    // EVERYONE: ADMIN, MANAGER, CASHIER
    // ========================================================

    Route::middleware('role:admin,manager,cashier')->group(function () {

        // Dashboard
        Route::get('dashboard', [TransactionController::class, 'dashboard'])
            ->name('dashboard');

        // Action Requests
        Route::get('action-requests', [ActionRequestController::class, 'index'])
            ->name('action-requests');

        Route::post('action-requests', [ActionRequestController::class, 'store'])
            ->middleware('role:manager')
            ->name('action-requests.store');

        Route::patch('action-requests/{actionRequest}/review', [ActionRequestController::class, 'review'])
            ->middleware('role:admin')
            ->name('action-requests.review');

        // Clock In / Break / Clock Out
        Route::patch('clock-status', [ClockStatusController::class, 'update'])
            ->name('clock-status.update');

        // Sale Transaction
        Route::get('sale-transaction', [TransactionController::class, 'saleTransaction'])
            ->name('sale-transaction');
    });

    // ========================================================
    // CASHIER ONLY
    // ========================================================

    Route::middleware('role:cashier')->group(function () {

        // Read-only Product View
        Route::get('cashier/products', [ProductController::class, 'cashierIndex'])
            ->name('cashier.products.index');

        // Cashier Transactions
        Route::post('cashier/transactions', [TransactionController::class, 'store'])
            ->name('cashier.transactions.store');

        // Cashier Transaction History
        Route::get('cashier/transactions/history', [TransactionController::class, 'cashierHistory'])
            ->name('cashier.transactions.history');
    });

    // ========================================================
    // ADMIN + MANAGER ONLY
    // ========================================================

    Route::middleware('role:admin,manager')->group(function () {

        // ====================================================
        // SALES / INCOME REPORTS
        // ====================================================

        Route::get('reports/income', [IncomeReportController::class, 'index'])
            ->name('reports.income');

        Route::get('reports/sales', [TransactionController::class, 'salesReport'])
            ->name('reports.sales');

        // ----------------------------------------------------
        // SALES REPORT — FULL EXCEL
        // ----------------------------------------------------

        Route::get(
            'reports/sales/export',
            [TransactionController::class, 'exportSales']
        )->name('reports.sales.export');

        // ----------------------------------------------------
        // SALES REPORT — FULL WORD
        // ----------------------------------------------------

        Route::get(
            'reports/sales/export/word',
            [TransactionController::class, 'exportSalesWord']
        )->name('reports.sales.export.word');

        // ----------------------------------------------------
        // SALES REPORT — SINGLE WORD SECTION
        // ----------------------------------------------------

        Route::get(
            'reports/sales/export/word/{sheet}',
            [TransactionController::class, 'exportSalesWordSection']
        )
            ->where(
                'sheet',
                'summary|transaction-log|product-sales|payment-summary|sales-by-period'
            )
            ->name('reports.sales.export.word.sheet');

        // ----------------------------------------------------
        // SALES REPORT — SINGLE EXCEL SECTION
        // ----------------------------------------------------

        Route::get(
            'reports/sales/export/{sheet}',
            [TransactionController::class, 'exportSalesSheet']
        )
            ->where(
                'sheet',
                'summary|transaction-log|product-sales|payment-summary|sales-by-period'
            )
            ->name('reports.sales.export.sheet');

        Route::get('sales-history', [TransactionController::class, 'history'])
            ->name('sales-history');

        // ====================================================
        // PRODUCTS
        // ====================================================

        Route::get('products', [ProductController::class, 'index'])
            ->name('products.index');

        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::put('products/{product}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('products.destroy');

        // ====================================================
        // INGREDIENTS
        // ====================================================

        Route::get('inventory/ingredients', [IngredientController::class, 'index'])
            ->name('inventory.ingredients');

        Route::post('inventory/ingredients', [IngredientController::class, 'store'])
            ->name('inventory.ingredients.store');

        Route::put('inventory/ingredients/{ingredient}', [IngredientController::class, 'update'])
            ->name('inventory.ingredients.update');

        Route::delete('inventory/ingredients/{ingredient}', [IngredientController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('inventory.ingredients.destroy');

        // ====================================================
        // INGREDIENT BATCHES
        // ====================================================

        Route::get(
            'inventory/ingredients/{ingredient}/batches',
            [IngredientController::class, 'batches']
        )->name('inventory.ingredients.batches');

        Route::post(
            'inventory/ingredients/{ingredient}/batches',
            [IngredientController::class, 'restock']
        )
            ->middleware('role:admin')
            ->name('inventory.ingredients.restock');

        // ====================================================
        // CATEGORIES
        // ====================================================

        Route::post('categories', [CategoryController::class, 'store'])
            ->name('categories.store');

        Route::put('categories/{category}', [CategoryController::class, 'update'])
            ->name('categories.update');

        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('categories.destroy');

        // ====================================================
        // COOKIE INVENTORY
        // ====================================================

        Route::get('inventory/cookies', [CookieController::class, 'index'])
            ->name('inventory.cookies');

        Route::post(
            'inventory/cookies/{product}/stock',
            [CookieController::class, 'adjustStock']
        )
            ->middleware('role:admin')
            ->name('inventory.cookies.adjust-stock');

        // ====================================================
        // REFUND / VOID
        // ====================================================

        Route::patch(
            'transactions/{transaction}/refund',
            [TransactionController::class, 'refund']
        )
            ->middleware('role:admin')
            ->name('transactions.refund');

        Route::patch(
            'transactions/{transaction}/void',
            [TransactionController::class, 'void']
        )
            ->middleware('role:admin')
            ->name('transactions.void');

        // ====================================================
        // INVENTORY REPORT
        // ====================================================

        // Inventory Report Page
        Route::get(
            'reports/inventory',
            [IngredientController::class, 'inventoryReport']
        )->name('reports.inventory');

        // ----------------------------------------------------
        // FULL EXCEL REPORT
        // ----------------------------------------------------

        Route::get(
            'reports/inventory/export',
            [IngredientController::class, 'exportInventory']
        )->name('reports.inventory.export');

        // ----------------------------------------------------
        // FULL WORD REPORT
        // ----------------------------------------------------

        Route::get(
            'reports/inventory/export/word',
            [IngredientController::class, 'exportInventoryWord']
        )->name('reports.inventory.export.word');

        // ----------------------------------------------------
        // SINGLE WORD SECTION
        // ----------------------------------------------------

        Route::get(
            'reports/inventory/export/word/{sheet}',
            [IngredientController::class, 'exportInventoryWordSection']
        )
            ->where(
                'sheet',
                'summary|stock-in|movement|batch-expiry|low-stock'
            )
            ->name('reports.inventory.export.word.sheet');

        // ----------------------------------------------------
        // SINGLE EXCEL SECTION
        // ----------------------------------------------------

        Route::get(
            'reports/inventory/export/{sheet}',
            [IngredientController::class, 'exportInventorySheet']
        )
            ->where(
                'sheet',
                'summary|stock-in|movement|batch-expiry|low-stock'
            )
            ->name('reports.inventory.export.sheet');

        // ====================================================
        // RESTOCK HISTORY
        // ====================================================

        Route::get(
            'reports/restock-history',
            [IngredientController::class, 'restockHistory']
        )->name('reports.restock-history');

        // ====================================================
        // ATTENDANCE REPORT
        // ====================================================

        Route::get(
            'reports/attendance',
            [AttendanceReportController::class, 'index']
        )->name('reports.attendance');
    });

    // ========================================================
    // ADMIN ONLY — USER MANAGEMENT
    // ========================================================

    Route::middleware('role:admin')
        ->prefix('user-management')
        ->name('user-management.')
        ->group(function () {

            Route::get('/', [UserManagementController::class, 'index'])
                ->name('index');

            Route::get('/create', [UserManagementController::class, 'create'])
                ->name('create');

            Route::post('/', [UserManagementController::class, 'store'])
                ->name('store');

            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])
                ->name('edit');

            Route::put('/{user}', [UserManagementController::class, 'update'])
                ->name('update');

            Route::delete('/{user}', [UserManagementController::class, 'destroy'])
                ->name('destroy');

            // Employee Activity Logs
            Route::get('/{user}/logs', [UserManagementController::class, 'activityLogs'])
                ->name('logs');

            // Employee Schedule
            Route::get('/{user}/schedule', [ScheduleController::class, 'edit'])
                ->name('schedule.edit');

            Route::put('/{user}/schedule', [ScheduleController::class, 'update'])
                ->name('schedule.update');
        });
});

// ============================================================
// SETTINGS
// ============================================================

require __DIR__ . '/settings.php';

// ============================================================
// AUTHENTICATION
// ============================================================

require __DIR__ . '/auth.php';