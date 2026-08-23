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

// Public Routes

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Authenticated Routes

Route::middleware(['auth', 'verified'])->group(function () {

    // Everyone: Admin, Manager, Cashier
    Route::middleware('role:admin,manager,cashier')->group(function () {

        // Dashboard - KEEP
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

        // Clock in / break / clock out — any employee updates their own status
        Route::patch('clock-status', [ClockStatusController::class, 'update'])
            ->name('clock-status.update');

        // Sale Transaction
        Route::get('sale-transaction', [TransactionController::class, 'saleTransaction'])
            ->name('sale-transaction');
    });

    // Cashier Only — Read-only Product View + Transactions
    Route::middleware('role:cashier')->group(function () {
        Route::get('cashier/products', [ProductController::class, 'cashierIndex'])
            ->name('cashier.products.index');

        Route::post('cashier/transactions', [TransactionController::class, 'store'])
            ->name('cashier.transactions.store');

        // Transaction History — the cashier's own past transactions, with
        // All / Completed / Refunded / Voided filters.
        Route::get('cashier/transactions/history', [TransactionController::class, 'cashierHistory'])
            ->name('cashier.transactions.history');
    });

    // Admin + Manager Only
    Route::middleware('role:admin,manager')->group(function () {

        // Sales / Income Reports
        Route::get('reports/income', [IncomeReportController::class, 'index'])
            ->name('reports.income');

        // Products (full management page)
        Route::get('products', [ProductController::class, 'index'])
            ->name('products.index');

        Route::post('products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::put('products/{product}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('products.destroy');

        // Ingredients
        Route::get('inventory/ingredients', [IngredientController::class, 'index'])
            ->name('inventory.ingredients');

        Route::post('inventory/ingredients', [IngredientController::class, 'store'])
            ->name('inventory.ingredients.store');

        Route::put('inventory/ingredients/{ingredient}', [IngredientController::class, 'update'])
            ->name('inventory.ingredients.update');

        Route::delete('inventory/ingredients/{ingredient}', [IngredientController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('inventory.ingredients.destroy');

        // Ingredient Batches
        Route::get(
            'inventory/ingredients/{ingredient}/batches',
            [IngredientController::class, 'batches']
        )->name('inventory.ingredients.batches');

        Route::post(
            'inventory/ingredients/{ingredient}/batches',
            [IngredientController::class, 'restock']
        )->middleware('role:admin')->name('inventory.ingredients.restock');

        // Categories
        Route::post('categories', [CategoryController::class, 'store'])
            ->name('categories.store');

        Route::put('categories/{category}', [CategoryController::class, 'update'])
            ->name('categories.update');

        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('role:admin')
            ->name('categories.destroy');

        // Cookie Inventory (finished_stock products — simple on-hand count,
        // no batches/expiry; distinct from the Ingredients batch system)
        Route::get('inventory/cookies', [CookieController::class, 'index'])
            ->name('inventory.cookies');

        Route::post('inventory/cookies/{product}/stock', [CookieController::class, 'adjustStock'])
            ->middleware('role:admin')
            ->name('inventory.cookies.adjust-stock');

        // Sales Reports
        Route::get('reports/sales', [TransactionController::class, 'salesReport'])
            ->name('reports.sales');

        // Sales History
        Route::get('sales-history', [TransactionController::class, 'history'])
            ->name('sales-history');

        // Refund Transaction
        Route::patch('transactions/{transaction}/refund', [TransactionController::class, 'refund'])
            ->middleware('role:admin')
            ->name('transactions.refund');

        // Void Transaction
        Route::patch('transactions/{transaction}/void', [TransactionController::class, 'void'])
            ->middleware('role:admin')
            ->name('transactions.void');

        // Inventory Reports
        Route::get('reports/inventory', [IngredientController::class, 'inventoryReport'])
            ->name('reports.inventory');

        // Restock History Report
        Route::get('reports/restock-history', [IngredientController::class, 'restockHistory'])
            ->name('reports.restock-history');

        // Attendance Report — Late / Undertime / Overtime / Break / Total Hours per employee
        Route::get('reports/attendance', [AttendanceReportController::class, 'index'])
            ->name('reports.attendance');
    });

    // Admin Only — User Management
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

            // Full activity timeline (login/break/logout) for one employee — powers the "View Records" modal
            Route::get('/{user}/logs', [UserManagementController::class, 'activityLogs'])
                ->name('logs');

            // Weekly schedule (per day of week: expected time in/out, or day-off)
            Route::get('/{user}/schedule', [ScheduleController::class, 'edit'])
                ->name('schedule.edit');

            Route::put('/{user}/schedule', [ScheduleController::class, 'update'])
                ->name('schedule.update');
        });
});

    // Settings

require __DIR__.'/settings.php';

// Authentication

require __DIR__.'/auth.php';