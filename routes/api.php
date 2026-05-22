<?php

use App\Http\Controllers\Api\V1\ShopController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    
    // Các Route yêu cầu Buyer phải đăng nhập qua Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        
        // API Đăng ký mở shop
        Route::post('shops/register', [ShopController::class, 'register']);
        
    });
});