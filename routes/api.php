<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PublicProductController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('products', [PublicProductController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('shops/register', [ShopController::class, 'register']);
        Route::get('seller/products', [ProductController::class, 'index']);
        Route::put('seller/products/{id}', [ProductController::class, 'update']);
        Route::delete('seller/products/{id}', [ProductController::class, 'destroy']);
        Route::patch('seller/orders/{id}', [SellerOrderController::class, 'updateStatus']);
        Route::post('products', [ProductController::class, 'store']);

        Route::prefix('admin')->group(function () {
            Route::get('shops', [AdminController::class, 'listShops']);
            Route::patch('shops/{shop}/status', [AdminController::class, 'updateShopStatus']);
            Route::patch('users/{user}', [AdminController::class, 'updateUserStatus']);
            Route::patch('products/{product}', [AdminController::class, 'updateProductStatus']);
            Route::get('orders', [AdminController::class, 'listOrders']);
        });
    });
});
