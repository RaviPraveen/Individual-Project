<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BillingSettingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionAnalyticsController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\PromotionPosterController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReceiptSettingController;
use App\Http\Controllers\Admin\ReorderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierReturnController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\Cashier\BillingController;
use App\Http\Controllers\Cashier\CustomerDisplayController;
use App\Http\Controllers\Cashier\DashboardController as CashierDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return request()->user()->role === 'admin'
        ? redirect()->route('admin.dashboard')
        : redirect()->route('cashier.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', CategoryController::class)->except('show');

        Route::resource('products', ProductController::class)->except('show');
        Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
        Route::get('products-import', [ProductController::class, 'importForm'])->name('products.import.form');
        Route::post('products-import', [ProductController::class, 'import'])->name('products.import');

        Route::resource('suppliers', SupplierController::class)->except('show');

        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('purchase-orders/{purchase_order}/mark-received', [PurchaseOrderController::class, 'markReceived'])->name('purchase-orders.mark-received');
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        Route::resource('supplier-returns', SupplierReturnController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('supplier-returns/{supplier_return}/complete', [SupplierReturnController::class, 'complete'])->name('supplier-returns.complete');
        Route::post('supplier-returns/{supplier_return}/cancel', [SupplierReturnController::class, 'cancel'])->name('supplier-returns.cancel');
        Route::get('supplier-returns/{supplier_return}/pdf', [SupplierReturnController::class, 'pdf'])->name('supplier-returns.pdf');

        Route::get('/reorder-assistant', [ReorderController::class, 'index'])->name('reorder.index');

        Route::resource('customers', CustomerController::class)->except('show');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('/by-product', [ReportController::class, 'byProduct'])->name('by-product');
            Route::get('/by-category', [ReportController::class, 'byCategory'])->name('by-category');
            Route::get('/by-cashier', [ReportController::class, 'byCashier'])->name('by-cashier');
            Route::get('/low-stock', [ReportController::class, 'lowStock'])->name('low-stock');
            Route::get('/near-expiry', [ReportController::class, 'nearExpiry'])->name('near-expiry');
            Route::get('/dead-stock', [ReportController::class, 'deadStock'])->name('dead-stock');
            Route::get('/profit', [ReportController::class, 'profit'])->name('profit');
        });

        Route::get('promotions-analytics', [PromotionAnalyticsController::class, 'index'])->name('promotions.analytics');
        Route::resource('promotions', PromotionController::class)->except('show');
        Route::post('promotions/{promotion}/toggle-status', [PromotionController::class, 'toggleStatus'])->name('promotions.toggle-status');
        Route::post('promotions-bulk-action', [PromotionController::class, 'bulkAction'])->name('promotions.bulk-action');
        Route::post('promotions/{promotion}/poster/generate', [PromotionPosterController::class, 'generate'])->name('promotions.poster.generate');
        Route::post('promotions/{promotion}/poster/approve', [PromotionPosterController::class, 'approve'])->name('promotions.poster.approve');
        Route::post('promotions/{promotion}/poster/discard', [PromotionPosterController::class, 'discard'])->name('promotions.poster.discard');

        Route::prefix('revenue')->name('revenue.')->group(function () {
            Route::get('/', [RevenueController::class, 'index'])->name('index');
            Route::get('/by-period', [RevenueController::class, 'byPeriod'])->name('by-period');
            Route::get('/by-product', [RevenueController::class, 'byProduct'])->name('by-product');
            Route::get('/by-category', [RevenueController::class, 'byCategory'])->name('by-category');
        });

        Route::get('/forecasts', [ForecastController::class, 'index'])->name('forecasts.index');
        Route::get('/forecasts/{product}', [ForecastController::class, 'show'])->name('forecasts.show');

        Route::get('/customers/{customer}/behavior', [CustomerController::class, 'behavior'])->name('customers.behavior');

        Route::get('/ai-chat', [AiChatController::class, 'index'])->name('ai-chat.index');
        Route::post('/ai-chat', [AiChatController::class, 'ask'])->name('ai-chat.ask');
        Route::get('/ai-chat/conversations/{conversation}', [AiChatController::class, 'switchConversation'])->name('ai-chat.conversations.switch');
        Route::patch('/ai-chat/conversations/{conversation}', [AiChatController::class, 'renameConversation'])->name('ai-chat.conversations.rename');
        Route::delete('/ai-chat/conversations/{conversation}', [AiChatController::class, 'deleteConversation'])->name('ai-chat.conversations.delete');
        Route::delete('/ai-chat/conversations/{conversation}/messages', [AiChatController::class, 'clearConversation'])->name('ai-chat.conversations.clear');
        Route::patch('/ai-chat/messages/{log}/feedback', [AiChatController::class, 'feedback'])->name('ai-chat.messages.feedback');

        Route::prefix('receipt-settings')->name('receipt-settings.')->group(function () {
            Route::get('/', [ReceiptSettingController::class, 'edit'])->name('edit');
            Route::put('/', [ReceiptSettingController::class, 'update'])->name('update');
            Route::post('/reset', [ReceiptSettingController::class, 'reset'])->name('reset');
            Route::post('/logo', [ReceiptSettingController::class, 'uploadLogo'])->name('logo.upload');
            Route::delete('/logo', [ReceiptSettingController::class, 'removeLogo'])->name('logo.remove');
            Route::get('/pdf', [ReceiptSettingController::class, 'pdf'])->name('pdf');
        });

        Route::prefix('billing-settings')->name('billing-settings.')->group(function () {
            Route::get('/', [BillingSettingController::class, 'edit'])->name('edit');
            Route::put('/', [BillingSettingController::class, 'update'])->name('update');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingController::class, 'edit'])->name('edit');
            Route::put('/', [SettingController::class, 'update'])->name('update');
        });

        // User Management (admin only)
        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    });

Route::middleware(['auth', 'verified', 'role:admin,cashier'])->group(function () {
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');

    Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/lookup', [ReturnController::class, 'lookup'])->name('returns.lookup');
    Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{saleReturn}', [ReturnController::class, 'show'])->name('returns.show');
});

Route::middleware(['auth', 'verified', 'role:cashier'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {
        Route::get('/dashboard', [CashierDashboardController::class, 'index'])->name('dashboard');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing', [BillingController::class, 'store'])->name('billing.store');
        Route::get('/billing/{sale}/receipt', [BillingController::class, 'receipt'])->name('billing.receipt');
        Route::get('/billing/{sale}/receipt/pdf', [BillingController::class, 'receiptPdf'])->name('billing.receipt.pdf');
        Route::post('/customers/quick-create', [BillingController::class, 'quickCreateCustomer'])->name('customers.quick-create');

        Route::post('/billing/upsell-suggestion', [BillingController::class, 'upsellSuggestion'])->name('billing.upsell');
        Route::post('/billing/parse-order', [BillingController::class, 'parseOrderText'])->name('billing.parse-order');

        Route::get('/display', [CustomerDisplayController::class, 'show'])->name('display.show');
        Route::get('/display/data', [CustomerDisplayController::class, 'data'])->name('display.data');
        Route::post('/display/sync', [CustomerDisplayController::class, 'sync'])->name('display.sync');
        Route::get('/display/promotions', [CustomerDisplayController::class, 'promotions'])->name('display.promotions');
        Route::post('/display/promotions/{promotion}/viewed', [CustomerDisplayController::class, 'markPromotionViewed'])->name('display.promotions.viewed');

        Route::get('/ai-chat', [AiChatController::class, 'index'])->name('ai-chat.index');
        Route::post('/ai-chat', [AiChatController::class, 'ask'])->name('ai-chat.ask');
        Route::get('/ai-chat/conversations/{conversation}', [AiChatController::class, 'switchConversation'])->name('ai-chat.conversations.switch');
        Route::patch('/ai-chat/conversations/{conversation}', [AiChatController::class, 'renameConversation'])->name('ai-chat.conversations.rename');
        Route::delete('/ai-chat/conversations/{conversation}', [AiChatController::class, 'deleteConversation'])->name('ai-chat.conversations.delete');
        Route::delete('/ai-chat/conversations/{conversation}/messages', [AiChatController::class, 'clearConversation'])->name('ai-chat.conversations.clear');
        Route::patch('/ai-chat/messages/{log}/feedback', [AiChatController::class, 'feedback'])->name('ai-chat.messages.feedback');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

require __DIR__.'/auth.php';
