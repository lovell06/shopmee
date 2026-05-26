<?php

use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // === PUBLIC ROUTES (Không cần đăng nhập) ===
    Route::post('auth/login', [AuthController::class, 'login']);


    // === PROTECTED ROUTES (Bắt buộc phải có token Sanctum) ===
    Route::middleware('auth:sanctum')->group(function () {

        // API Đăng xuất tài khoản
        Route::post('auth/logout', [AuthController::class, 'logout']);
        
        // API Đăng ký mở shop
        Route::post('shops/register', [ShopController::class, 'register']);

        // API Danh sách sản phẩm của Shop (Seller)
        Route::get('seller/products', [ProductController::class, 'index']);
        
    });
});