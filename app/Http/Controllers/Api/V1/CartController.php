<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddToCartRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    #[OA\Post(
        path: "/cart/add",
        summary: "Thêm sản phẩm vào giỏ hàng",
        description: "Thêm sản phẩm/biến thể với số lượng mong muốn vào giỏ hàng của Buyer.",
        operationId: "addToCart",
        tags: ["Cart"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product_variant_id", "quantity"],
                properties: [
                    new OA\Property(property: "product_variant_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 2)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đã thêm sản phẩm vào giỏ hàng thành công!")
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Yêu cầu không hợp lệ (Ví dụ: Vượt quá số lượng tồn kho)"
            ),
            new OA\Response(
                response: 404,
                description: "Không tìm thấy sản phẩm/biến thể"
            )
        ]
    )]
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

    #[OA\Get(
        path: "/cart",
        summary: "Xem danh sách giỏ hàng",
        description: "Lấy danh sách các mặt hàng trong giỏ hàng của Buyer hiện tại, được phân nhóm theo Shop.",
        operationId: "getCart",
        tags: ["Cart"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Lấy danh sách giỏ hàng thành công."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
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
