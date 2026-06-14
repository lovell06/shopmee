<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PublicProductController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use App\Http\Controllers\Api\V1\SellerDashboardController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductReviewController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('products', [PublicProductController::class, 'index']);
    Route::get('products/search', [PublicProductController::class, 'search']);
    Route::get('products/{id}', [PublicProductController::class, 'show']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('register/verify', [AuthController::class, 'verify']);
    // Luồng Quên mật khẩu trọn gói của bạn
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/verify', [AuthController::class, 'verifyPasswordOtp']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
    
    // Webhook IPN của MoMo (Không qua auth vì do MoMo Server gọi đến)
    Route::post('payments/momo-ipn', [OrderController::class, 'momoIpn']);

    Route::post('chat/gemini', [ChatController::class, 'ask']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('shops/register', [ShopController::class, 'register']);
        Route::get('seller/products', [ProductController::class, 'index']);
        Route::put('seller/products/{id}', [ProductController::class, 'update']);
        Route::delete('seller/products/{id}', [ProductController::class, 'destroy']);
        Route::get('seller/orders', [SellerOrderController::class, 'index']);
        Route::patch('seller/orders/{id}', [SellerOrderController::class, 'updateStatus']);
        Route::get('seller/dashboard/revenue', [SellerDashboardController::class, 'revenue']);
        Route::post('products', [ProductController::class, 'store']);
        // API giỏ hàng 
        Route::post('cart/add', [CartController::class, 'store']);
        Route::get('cart', [CartController::class, 'index']);
        Route::put('cart/update', [CartController::class, 'updateQuantity']);
        Route::delete('cart/{id}', [CartController::class, 'destroy']);
        Route::post('cart/bulk-delete', [CartController::class, 'bulkDestroy']);
        Route::get('cart/count', [CartController::class, 'count']);
        // Hệ thống API quản lý sổ địa chỉ
        Route::get('addresses', [AddressController::class, 'index']);       // Lấy danh sách
        Route::post('addresses/add', [AddressController::class, 'store']);      // Thêm mới
        Route::put('addresses/{id}', [AddressController::class, 'update']); // Sửa đổi
        Route::delete('addresses/{id}', [AddressController::class, 'destroy']); // Xóa bỏ
        // API Đơn hàng & Giả lập thanh toán 
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::post('payments/simulate', [OrderController::class, 'simulatePayment']);
        Route::post('payments/momo-verify', [OrderController::class, 'momoVerify']);
        Route::get('orders/{id}/status', [OrderController::class, 'checkStatus']);
        
        Route::get('orders', [OrderController::class, 'index']); // Lấy danh sách lịch sử
        Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']); // Hủy đơn hàng theo ID
        Route::post('orders/{order_id}/products/{product_id}/review', [ProductReviewController::class, 'store']); // Đánh giá sản phẩm
        
        // API Hồ sơ người dùng
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);

        Route::prefix('admin')->group(function () {
            Route::get('shops', [AdminController::class, 'listShops']);
            Route::get('users', [AdminController::class, 'listUsers']);
            Route::get('products', [AdminController::class, 'listProducts']);
            Route::patch('shops/{shop}/status', [AdminController::class, 'updateShopStatus']);
            Route::patch('users/{user}', [AdminController::class, 'updateUserStatus']);
            Route::patch('products/{product}', [AdminController::class, 'updateProductStatus']);
            Route::get('orders', [AdminController::class, 'listOrders']);
            Route::get('revenue', [AdminController::class, 'revenue']);
        });
    });
});
