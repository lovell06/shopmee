<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class SellerOrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    #[OA\Patch(
        path: "/seller/orders/{id}",
        summary: "Cập nhật trạng thái đơn hàng (dành cho Seller)",
        description: "Seller cập nhật trạng thái đơn hàng của shop (confirmed, shipping, delivered, cancelled).",
        operationId: "updateOrderStatus",
        tags: ["Seller Orders"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của đơn hàng", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["confirmed", "shipping", "delivered", "cancelled"], example: "confirmed")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Cập nhật thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Cập nhật trạng thái đơn hàng thành công"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "order_id", type: "string", example: "1"),
                                new OA\Property(property: "status", type: "string", example: "confirmed")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Yêu cầu không hợp lệ"
            ),
            new OA\Response(
                response: 403,
                description: "Không có quyền chỉnh sửa đơn hàng này"
            ),
            new OA\Response(
                response: 404,
                description: "Không tìm thấy đơn hàng"
            )
        ]
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, $id)
    {
        try {
            $user = Auth::user();

            $order = $this->orderService->updateOrderStatus(
                $user->id,
                (int)$id,
                $request->input('status')
            );

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái đơn hàng thành công',
                'data' => [
                    'order_id' => (string)$order->id,
                    'status' => $order->status->value,
                ]
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống cập nhật đơn hàng: ' . $e->getMessage());
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

    #[OA\Get(
        path: "/seller/orders",
        summary: "Lấy danh sách đơn hàng của shop (dành cho Seller)",
        description: "Seller lấy danh sách các đơn hàng chứa sản phẩm từ shop của mình quản lý.",
        operationId: "getSellerOrders",
        tags: ["Seller Orders"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Tải danh sách đơn hàng của shop thành công."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Yêu cầu không hợp lệ"
            ),
            new OA\Response(
                response: 401,
                description: "Chưa xác thực"
            )
        ]
    )]
    public function index()
    {
        try {
            $userId = Auth::id();
            $orders = $this->orderService->getSellerOrders($userId);

            return response()->json([
                'success' => true,
                'message' => 'Tải danh sách đơn hàng của shop thành công.',
                'data' => $orders
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống lấy đơn hàng cho seller: ' . $e->getMessage());
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
