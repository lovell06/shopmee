<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopRegisterRequest;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Dùng để ghi log lỗi ẩn
use Exception;

class ShopController extends Controller
{
    public function register(ShopRegisterRequest $request)
    {
        try {
            // Lấy thông tin user
            $user = Auth::user();

            // Kiểm tra shop tồn tại
            $existsShop = Shop::query()->where('user_id', $user->id)->first();
            if ($existsShop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã đăng ký hoặc đã sở hữu một cửa hàng.'
                ], 400);
            }

            // Tạo shop mới
            $shop = Shop::create([
                'user_id'     => $user->id,
                'name'        => $request->name,
                'description' => $request->description,
                'logo'        => $request->logo,
                'status'      => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký mở cửa hàng thành công!',
                'data'    => [
                    'shop_id' => $shop->id,
                    'user_id' => $user->id
                ]
            ], 201);

        } catch (Exception $e) {
            // 1. Ghi lại lỗi chi tiết vào file storage/logs/laravel.log để dev xem và sửa
            Log::error('Lỗi đăng ký shop: ' . $e->getMessage());

            // 2. Trả về cấu trúc JSON sạch sẽ bảo mật cho Frontend
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố kỹ thuật. Vui lòng thử lại sau ít phút!'
            ], 500); // HTTP 500 Internal Server Error
        }
    }
}
