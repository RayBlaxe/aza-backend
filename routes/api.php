<?php

// routes/api.php

use App\Http\Controllers\Admin\CategoryManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderManagementController;
use App\Http\Controllers\Admin\ProductManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public product routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/latest', [ProductController::class, 'latest']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Public category routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// Public shipping routes
Route::get('/shipping/cities', [ShippingController::class, 'getSupportedCities']);
Route::get('/shipping/origin', [ShippingController::class, 'getOriginInfo']);
Route::get('/shipping/courier-services', [ShippingController::class, 'getCourierServices']);
Route::post('/shipping/calculate', [ShippingController::class, 'calculateShipping']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Cart routes
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getCart']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('/items/{cartItem}', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
    });

    // Order routes
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/payment', [OrderController::class, 'createPayment']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/{order}/status', [PaymentController::class, 'getPaymentStatus']);
    Route::get('/orders/{order}/tracking', [OrderController::class, 'getTracking']);
    Route::put('/orders/{order}/tracking', [OrderController::class, 'updateTracking']);

    // User Address routes
    Route::apiResource('user-addresses', UserAddressController::class);
    Route::post('user-addresses/{userAddress}/set-default', [UserAddressController::class, 'setDefault']);
    
    // Shipping routes (protected)
    Route::post('/shipping/calculate-cart', [ShippingController::class, 'calculateCartShipping']);
});

// Public routes
Route::post('/payment/notification', [PaymentController::class, 'handleNotification']);

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/stats', [DashboardController::class, 'getStats']);
    Route::get('/recent-orders', [DashboardController::class, 'getRecentOrders']);
    Route::get('/top-products', [DashboardController::class, 'getTopProducts']);
    Route::get('/sales-chart', [DashboardController::class, 'getSalesChart']);

    // Products Management
    Route::apiResource('products', ProductManagementController::class);
    Route::post('products/{product}/toggle-status', [ProductManagementController::class, 'toggleStatus']);
    Route::post('products/upload-image', [ProductManagementController::class, 'uploadImage']);
    Route::delete('products/bulk-delete', [ProductManagementController::class, 'bulkDelete']);
    Route::get('products/export/csv', [ProductManagementController::class, 'exportCsv']);

    // Orders Management
    Route::get('orders', [OrderManagementController::class, 'index']);
    Route::get('orders/{order}', [OrderManagementController::class, 'show']);
    Route::put('orders/{order}/status', [OrderManagementController::class, 'updateStatus']);
    Route::get('orders/by-status', [OrderManagementController::class, 'getOrdersByStatus']);
    Route::post('orders/bulk-update-status', [OrderManagementController::class, 'bulkUpdateStatus']);
    Route::get('orders/export/csv', [OrderManagementController::class, 'exportCsv']);

    // Users Management
    Route::apiResource('users', UserManagementController::class);
    Route::put('users/{user}/role', [UserManagementController::class, 'updateRole']);
    Route::get('customers/stats', [UserManagementController::class, 'getCustomerStats']);
    Route::delete('users/bulk-delete', [UserManagementController::class, 'bulkDelete']);
    Route::get('users/export/csv', [UserManagementController::class, 'exportCsv']);

    // Categories Management
    Route::apiResource('categories', CategoryManagementController::class);
    Route::post('categories/{category}/toggle-status', [CategoryManagementController::class, 'toggleStatus']);
    Route::delete('categories/bulk-delete', [CategoryManagementController::class, 'bulkDelete']);
    Route::get('categories/export/csv', [CategoryManagementController::class, 'exportCsv']);
    Route::get('categories/statistics', [CategoryManagementController::class, 'getStatistics']);
});
