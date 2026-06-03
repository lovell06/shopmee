<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PublicProductController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use App\Http\Controllers\Api\V1\SellerDashboardController;
use App\Http\Controllers\Api\V1\CartController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('products', [PublicProductController::class, 'index']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('register/verify', [AuthController::class, 'verify']);
    // Luồng Quên mật khẩu trọn gói của bạn
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/verify', [AuthController::class, 'verifyPasswordOtp']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('shops/register', [ShopController::class, 'register']);
        Route::get('seller/products', [ProductController::class, 'index']);
        Route::put('seller/products/{id}', [ProductController::class, 'update']);
        Route::delete('seller/products/{id}', [ProductController::class, 'destroy']);
        Route::patch('seller/orders/{id}', [SellerOrderController::class, 'updateStatus']);
        Route::get('seller/dashboard/revenue', [SellerDashboardController::class, 'revenue']);
        Route::post('products', [ProductController::class, 'store']);
        // API Thêm vào giỏ hàng 
        Route::post('cart/add', [CartController::class, 'store']);
        Route::get('cart', [CartController::class, 'index']);

        Route::prefix('admin')->group(function () {
            Route::get('shops', [AdminController::class, 'listShops']);
            Route::patch('shops/{shop}/status', [AdminController::class, 'updateShopStatus']);
            Route::patch('users/{user}', [AdminController::class, 'updateUserStatus']);
            Route::patch('products/{product}', [AdminController::class, 'updateProductStatus']);
            Route::get('orders', [AdminController::class, 'listOrders']);
        });
    });
});
