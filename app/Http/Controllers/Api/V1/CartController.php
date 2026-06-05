<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddToCartRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * API Thêm sản phẩm vào giỏ hàng
     */
    public function store(AddToCartRequest $request): JsonResponse
    {
        try {
            // Lấy ID người dùng đang đăng nhập thông qua Token Sanctum
            $user = Auth::user();
            
            // Gọi Service xử lý logic nghiệp vụ
            $this->cartService->addToCart($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm sản phẩm vào giỏ hàng thành công!'
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            
            if ($statusCode === 500) {
                Log::error('Lỗi API thêm giỏ hàng: ' . $e->getMessage());
                $message = 'Hệ thống giỏ hàng đang gặp sự cố. Vui lòng thử lại sau!';
            } else {
                $message = $e->getMessage();
            }

            return response()->json([
                'success' => false,
                'message' => $message
            ], $statusCode);
        }
    }

    /**
    * API Xem danh sách giỏ hàng phân loại theo Shop
    * URL: GET /api/v1/cart
    */
    public function index(): JsonResponse
    {
        try {
            // Lấy thông tin user từ token sanctum bảo mật
            $user = Auth::user();

            // Gọi service xử lý thuật toán gom nhóm shop
            $cartData = $this->cartService->getCartGroupedByShop($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách giỏ hàng thành công.',
                'data'    => $cartData
            ], 200);

        } catch (Exception $e) {
            Log::error('Lỗi API xem giỏ hàng: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải dữ liệu giỏ hàng lúc này.'
            ], 500);
        }
    }
}
