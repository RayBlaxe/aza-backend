<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Products routes (to be added)
    // Route::apiResource('products', ProductController::class);
    
    // Cart routes (to be added)
    // Route::prefix('cart')->group(function () {
    //     Route::get('/', [CartController::class, 'index']);
    //     Route::post('/items', [CartController::class, 'addItem']);
    //     Route::put('/items/{id}', [CartController::class, 'updateItem']);
    //     Route::delete('/items/{id}', [CartController::class, 'removeItem']);
    // });
    
    // Orders routes (to be added)
    // Route::apiResource('orders', OrderController::class);
});

// Admin routes (to be added)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Admin specific routes will go here
});