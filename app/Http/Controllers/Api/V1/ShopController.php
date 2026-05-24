<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopRegisterRequest;
use App\Services\ShopService; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopController extends Controller
{
    protected ShopService $shopService;

    // Nạp Service vào Controller thông qua Constructor Injection
    public function __construct(ShopService $shopService)
    {
        $this->shopService = $shopService;
    }

    public function register(ShopRegisterRequest $request)
    {
        try {
            $user = Auth::user();

            // Controller CHỈ gọi Service xử lý và truyền dữ liệu thô vào
            $shop = $this->shopService->registerShop($request->validated(), $user->id);

            // Trả về response thành công
            return response()->json([
                'success' => true,
                'message' => 'Đăng ký mở cửa hàng thành công!',
                'data'    => [
                    'shop_id' => $shop->id,
                    'user_id' => $user->id
                ]
            ], 201);

        } catch (Exception $e) {
            // Nếu lỗi do logic hệ thống (mã 400 từ Service quăng ra)
            $statusCode = $e->getCode() == 400 ? 400 : 500;
            
            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống đăng ký shop: ' . $e->getMessage());
                $message = 'Hệ thống đang gặp sự cố kỹ thuật. Vui lòng thử lại sau!';
            } else {
                $message = $e->getMessage();
            }

            return response()->json([
                'success' => false,
                'message' => $message
            ], $statusCode);
        }
    }
}
