<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserTransactionController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\UserController;
// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Product listing (public)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/category/{slug}', [ProductController::class, 'byCategory']);
Route::get('/products/search', [ProductController::class, 'search']);

// Categories (public)
Route::get('/categories', [CategoryController::class, 'index']);

// Brands (public)
Route::get('/brands', [BrandController::class, 'index']);

// Product reviews (public)
Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/user/profile', [AuthController::class, 'getProfile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::put('/user/preferences', [AuthController::class, 'updatePreferences']);
    Route::get('/users', [AuthController::class, 'allUsers']); // admin
    Route::put('/users/{id}/status', [AuthController::class, 'updateStatus']); // activate/deactivate
    
    // Cart  (customer)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'addItem']);
    Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    
    // Payment routes
    Route::post('/orders/{orderId}/payment/initiate', [PaymentController::class, 'initiatePayment']);
    Route::post('/payment/callback', [PaymentController::class, 'handlePaymentCallback']);
    Route::get('/orders/{orderId}/payment/status', [PaymentController::class, 'getPaymentStatus']);
    Route::post('/orders/{orderId}/payment/retry', [PaymentController::class, 'retryPayment']);
    Route::post('/payment/mock/{transactionId}/complete', [PaymentController::class, 'mockPaymentComplete']);
    Route::get('/payment/methods', [PaymentController::class, 'getPaymentMethods']);
    Route::get('/payment/transactions', [PaymentController::class, 'getTransactionHistory']);
    
    // Order  (customer)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/history', [OrderController::class, 'history']); // customer
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/return', [OrderController::class, 'return']);
    Route::post('/orders/{id}/track', [OrderController::class, 'track']);
    Route::get('/payment-methods', [OrderController::class, 'getPaymentMethods']);
    
    // Notification (all authenticated users)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    
    // Wishlist routes
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{productId}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
    
    // User transaction history routes
    Route::get('/user/transactions', [UserTransactionController::class, 'index']);
    Route::get('/user/transactions/{id}', [UserTransactionController::class, 'show']);
    Route::get('/user/transactions/summary', [UserTransactionController::class, 'summary']);
    Route::post('/user/transactions/search-by-ticket', [UserTransactionController::class, 'searchByTicket']);
    
    // Review routes
    Route::post('/products/{id}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    
    // Category management (admin)
    Route::post('/categories', [CategoryController::class, 'store']); // admin
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    
    // Brand management (admin)
    Route::post('/brands', [BrandController::class, 'store']); // admin
    Route::put('/brands/{id}', [BrandController::class, 'update']);
    Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
    
    // Product image management
    Route::post('/products/{id}/images', [ProductController::class, 'uploadImage']);
    Route::delete('/products/{id}/images/{imageId}', [ProductController::class, 'deleteImage']);
    
    // Dashboard (role-specific)
    Route::get('/dashboard/customer', [DashboardController::class, 'customerDashboard']);
    
    // Admin dashboard routes
    Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard']);
    Route::get('/dashboard/admin/income-analytics', [DashboardController::class, 'getIncomeAnalytics']);
    Route::get('/dashboard/admin/product-history', [DashboardController::class, 'getProductOrderHistory']);
    Route::get('/dashboard/admin/category-analytics', [DashboardController::class, 'getCategoryAnalytics']);
    Route::get('/dashboard/admin/summary', [DashboardController::class, 'getDashboardSummary']);
    
    
    // Product management
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    
    // Request orders
    Route::get('/request-orders', [RequestOrderController::class, 'index']);
    Route::post('/request-orders', [RequestOrderController::class, 'store']);
    Route::get('/request-orders/{id}', [RequestOrderController::class, 'show']);
    Route::put('/request-orders/{id}/admin-approval', [RequestOrderController::class, 'adminApproval']);
    
    // Admin dashboard
    Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard']);
    
    
    // Request orders approval
    Route::put('/request-orders/{id}/warehouse-approval', [RequestOrderController::class, 'warehouseApproval']);
    
    // Warehouse dashboard
    Route::get('/dashboard/warehouse', [DashboardController::class, 'warehouseDashboard']);
    
    // Warehouse reorder management
    Route::get('/warehouse/reorders/pending', [WarehouseController::class, 'pendingReorders']);
    Route::get('/warehouse/reorders/{id}', [WarehouseController::class, 'showReorderRequest']);
    Route::post('/warehouse/reorders/{id}/approve', [WarehouseController::class, 'approveReorder']);
    Route::post('/warehouse/reorders/{id}/reject', [WarehouseController::class, 'rejectReorder']);
    Route::get('/warehouse/dashboard', [WarehouseController::class, 'dashboard']);
    Route::get('/warehouse/reorders/history', [WarehouseController::class, 'reorderHistory']);
  
    // Order processing
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::put('/orders/{id}/payment', [OrderController::class, 'updatePaymentStatus']);
    
    // Staff dashboard
    Route::get('/dashboard/staff', [DashboardController::class, 'staffDashboard']);
    
    // Staff management routes (admin only)
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::get('/staff/{id}', [StaffController::class, 'show']);
    Route::put('/staff/{id}', [StaffController::class, 'update']);
    Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
    
    // Staff dashboard and order management
    Route::get('/staff/dashboard', [StaffController::class, 'dashboard']);
    Route::get('/staff/orders/pending', [StaffController::class, 'getOrdersToReview']);
    Route::post('/staff/orders/{orderId}/approve', [StaffController::class, 'approveOrder']);
    Route::post('/staff/orders/{orderId}/reject', [StaffController::class, 'rejectOrder']);
    Route::get('/staff/income-analytics', [StaffController::class, 'getIncomeAnalytics']);
    
    // Transaction management routes (admin)
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::post('/transactions/search-by-ticket', [TransactionController::class, 'searchByTicket']);
    
    // Inventory management routes (admin)
    Route::get('/inventory/dashboard', [InventoryController::class, 'dashboard']);
    Route::get('/inventory/alerts', [InventoryController::class, 'getAlerts']);
    Route::put('/inventory/alerts/{id}/resolve', [InventoryController::class, 'resolveAlert']);
    Route::get('/inventory/reorder-requests', [InventoryController::class, 'getReorderRequests']);
    Route::post('/inventory/reorder-requests', [InventoryController::class, 'createReorderRequest']);
    Route::put('/inventory/reorder-requests/{id}/approve', [InventoryController::class, 'approveReorderRequest']);
    Route::put('/inventory/reorder-requests/{id}/complete', [InventoryController::class, 'completeReorderRequest']);
    Route::put('/inventory/reorder-requests/{id}/cancel', [InventoryController::class, 'cancelReorderRequest']);
    Route::put('/inventory/products/{id}/settings', [InventoryController::class, 'updateProductInventorySettings']);
    Route::put('/inventory/products/{id}/adjust-stock', [InventoryController::class, 'adjustStock']);
    Route::get('/inventory/low-stock-products', [InventoryController::class, 'getLowStockProducts']);
    Route::post('/inventory/send-low-stock-notifications', [InventoryController::class, 'sendLowStockNotifications']);
    
    // User management routes (admin)
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users/{id}/suspend', [UserController::class, 'suspend']);
    Route::post('/users/{id}/reactivate', [UserController::class, 'reactivate']);
    Route::get('/users/{id}/suspension-history', [UserController::class, 'getSuspensionHistory']);
    Route::get('/users/dashboard', [UserController::class, 'dashboard']);
    Route::post('/users/bulk-suspend', [UserController::class, 'bulkSuspend']);
    Route::post('/users/bulk-reactivate', [UserController::class, 'bulkReactivate']);
    
    // COMMENTED OUT ALL MIDDLEWARE GROUPS UNTIL THEY'RE PROPERLY SET UP
    /*
    // Admin routes
    Route::middleware('admin')->group(function () {
        // Product management
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
        
        // Request orders
        Route::get('/request-orders', [RequestOrderController::class, 'index']);
        Route::post('/request-orders', [RequestOrderController::class, 'store']);
        Route::get('/request-orders/{id}', [RequestOrderController::class, 'show']);
        Route::put('/request-orders/{id}/admin-approval', [RequestOrderController::class, 'adminApproval']);
        
        // Admin dashboard
        Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard']);
    });
    
    // Warehouse manager routes
    Route::middleware('warehouse')->group(function () {
        // Request orders approval
        Route::get('/request-orders', [RequestOrderController::class, 'index']);
        Route::get('/request-orders/{id}', [RequestOrderController::class, 'show']);
        Route::put('/request-orders/{id}/warehouse-approval', [RequestOrderController::class, 'warehouseApproval']);
        
        // Warehouse dashboard
        Route::get('/dashboard/warehouse', [DashboardController::class, 'warehouseDashboard']);
    });
    
    // Staff routes
    Route::middleware('staff')->group(function () {
        // Order processing
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::put('/orders/{id}/payment', [OrderController::class, 'updatePaymentStatus']);
        
        // Staff dashboard
        Route::get('/dashboard/staff', [DashboardController::class, 'staffDashboard']);
    });
    */
});