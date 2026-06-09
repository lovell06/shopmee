<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopRegisterRequest;
use App\Services\ShopService; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class ShopController extends Controller
{
    protected ShopService $shopService;

    // Nạp Service vào Controller thông qua Constructor Injection
    public function __construct(ShopService $shopService)
    {
        $this->shopService = $shopService;
    }

    #[OA\Post(
        path: "/shops/register",
        summary: "Đăng ký mở cửa hàng (Shop)",
        description: "Đăng ký thông tin cửa hàng cho tài khoản hiện tại.",
        operationId: "registerShop",
        tags: ["Shops"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Shop Quan Ao Dep"),
                    new OA\Property(property: "description", type: "string", example: "Chuyên cung cấp quần áo thời trang"),
                    new OA\Property(property: "logo_url", type: "string", example: "https://example.com/logo.png")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Đăng ký thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đăng ký mở cửa hàng thành công!"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "shop_id", type: "integer", example: 1),
                                new OA\Property(property: "user_id", type: "integer", example: 1)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Yêu cầu không hợp lệ hoặc tài khoản đã có Shop"
            )
        ]
    )]
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
