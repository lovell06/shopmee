<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    #[OA\Post(
        path: "/checkout",
        summary: "Đặt hàng (Checkout)",
        description: "Tiến hành checkout đặt hàng dựa theo giỏ hàng hiện tại.",
        operationId: "checkout",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["user_address_id", "payment_method"],
                properties: [
                    new OA\Property(property: "user_address_id", type: "string", example: "address-uuid-string"),
                    new OA\Property(property: "payment_method", type: "string", enum: ["cod", "bank_transfer", "momo"], example: "cod"),
                    new OA\Property(property: "description", type: "string", example: "Giao hàng giờ hành chính")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Đặt hàng thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đặt hàng thành công!"),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Giỏ hàng trống hoặc lỗi nghiệp vụ"
            )
        ]
    )]
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $validatedData = $request->validated();
            
            // Thực hiện cả tạo đơn hàng và tạo link MoMo trong cùng 1 Transaction để đảm bảo tính toàn vẹn dữ liệu
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $validatedData) {
                // 1. Tiến hành gọi dịch vụ xử lý đặt hàng (tạo đơn, trừ kho, xóa giỏ)
                $order = $this->orderService->processCheckout($userId, $validatedData);
                
                $payUrl = null;
                // 2. Gọi cổng thanh toán MoMo ngay trong transaction nếu chọn thanh toán qua ví
                if ($validatedData['payment_method'] === \App\Enums\PaymentMethod::Momo->value) {
                    $momoService = app(\App\Services\MomoService::class);
                    $payUrl = $momoService->createPaymentUrl($order);
                }
                
                return [
                    'order' => $order,
                    'payUrl' => $payUrl
                ];
            });

            $order = $result['order'];
            $payUrl = $result['payUrl'];
        
            // So sánh trực tiếp giá trị chuỗi gửi lên từ Request cho 
            if ($validatedData['payment_method'] === \App\Enums\PaymentMethod::BankTransfer->value) { 
                return response()->json([
                    'success' => true,
                    'message' => 'Đã thiết lập đơn hàng thành công! Hệ thống đang chờ bạn chuyển khoản giả lập.',
                    'data'    => [
                        'order_id'       => $order->id,
                        'total_amount'   => (float)$order->total_amount,
                        'payment_method' => $order->payment_method, 
                        'qr_simulation'  => "Vui lòng giả lập gửi tiền số tiền " . number_format($order->total_amount) . "đ với nội dung: SHOPMEE " . $order->id
                    ]
                ], 201);
            }

            if ($validatedData['payment_method'] === \App\Enums\PaymentMethod::Momo->value) {
                return response()->json([
                    'success' => true,
                    'message' => 'Đã thiết lập đơn hàng thành công! Đang chuyển hướng sang cổng thanh toán MoMo.',
                    'data'    => [
                        'order_id'       => $order->id,
                        'total_amount'   => (float)$order->total_amount,
                        'payment_method' => $order->payment_method,
                        'payUrl'         => $payUrl
                    ]
                ], 201);
            }
        
            // Nhánh mặc định còn lại (Dành cho cash_on_delivery hoặc các hình thức khác)
            return response()->json([
                'success' => true,
                'message' => 'Đặt hàng thành công! Đơn hàng của bạn chọn hình thức nhận tiền mặt (COD).',
                'data'    => [
                    'order_id'       => $order->id,
                    'total_amount'   => (float)$order->total_amount,
                    'payment_method' => $order->payment_method,
                ]
            ], 201);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            if ($statusCode === 500) {
                Log::error('Lỗi nghiêm trọng tại API Checkout: ' . $e->getMessage());
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    #[OA\Post(
        path: "/payments/simulate",
        summary: "Giả lập thanh toán chuyển khoản thành công",
        description: "API giả lập hành động webhook từ phía ngân hàng báo chuyển tiền thành công.",
        operationId: "simulatePayment",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["order_id"],
                properties: [
                    new OA\Property(property: "order_id", type: "integer", example: 1)
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
                        new OA\Property(property: "message", type: "string", example: "Xác nhận hệ thống giả lập: Giao dịch chuyển tiền ngân hàng thành công.")
                    ]
                )
            )
        ]
    )]
    public function simulatePayment(Request $request): JsonResponse
    {
        try {
            $request->validate(['order_id' => 'required|integer']);
            
            $userId = Auth::id();
            $this->orderService->simulatePaymentSuccess($request->order_id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Xác nhận hệ thống giả lập: Giao dịch chuyển tiền ngân hàng thành công. Đơn hàng đã chuyển trạng thái sang Đang xử lý!'
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    #[OA\Get(
        path: "/orders/{id}/status",
        summary: "Kiểm tra trạng thái đơn hàng",
        description: "Kiểm tra trạng thái thanh toán và giao hàng hiện tại của đơn hàng.",
        operationId: "checkOrderStatus",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của đơn hàng", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "payment_status", type: "string", example: "paid"),
                        new OA\Property(property: "status", type: "string", example: "processing")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Không tìm thấy đơn hàng"
            )
        ]
    )]
    public function checkStatus(int $id): JsonResponse
    {
        $order = Order::query()->where('id', $id)->where('user_id', Auth::id())->first();
        
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        return response()->json([
            'success' => true,
            'payment_status' => $order->payment_status,
            'status' => $order->status
        ], 200);
    }

    #[OA\Get(
        path: "/orders",
        summary: "Xem lịch sử đặt hàng",
        description: "Lấy danh sách lịch sử tất cả các đơn đặt hàng của Buyer.",
        operationId: "getUserOrders",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Tải danh sách lịch sử đơn hàng thành công."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $orders = $this->orderService->getUserOrderHistory($userId);

            return response()->json([
                'success' => true,
                'message' => 'Tải danh sách lịch sử đơn hàng thành công.',
                'data'    => $orders
            ], 200);
        } catch (Exception $e) {
            Log::error('Lỗi API lấy lịch sử đơn hàng: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi hệ thống, không thể tải đơn hàng.'], 500);
        }
    }   

    #[OA\Post(
        path: "/orders/{id}/cancel",
        summary: "Hủy đơn hàng",
        description: "Hủy đơn hàng hiện tại nếu đơn hàng chưa giao.",
        operationId: "cancelOrder",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của đơn hàng", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Hủy thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đã hủy đơn hàng thành công! Sản phẩm đã được hoàn trả lại vào kho.")
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Không thể hủy đơn hàng này"
            )
        ]
    )]
    public function cancel(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            $this->orderService->cancelOrder($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy đơn hàng thành công! Sản phẩm đã được hoàn trả lại vào kho.'
            ], 200);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    #[OA\Post(
        path: "/payments/momo-ipn",
        summary: "IPN Webhook từ MoMo",
        description: "Webhook nhận kết quả thanh toán bất đồng bộ từ MoMo.",
        operationId: "momoIpn",
        tags: ["Payments"],
        responses: [
            new OA\Response(response: 204, description: "Thành công"),
            new OA\Response(response: 400, description: "Chữ ký không hợp lệ")
        ]
    )]
    public function momoIpn(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        Log::info('MoMo IPN Callback payload: ' . json_encode($request->all()));
        
        $momoService = app(\App\Services\MomoService::class);
        
        if (!$momoService->verifyCallbackSignature($request->all())) {
            return response()->json([
                'success' => false,
                'message' => 'Chữ ký MoMo không hợp lệ.'
            ], 400);
        }
        
        $momoService->processPaymentResult($request->all());
        
        return response()->noContent();
    }

    #[OA\Post(
        path: "/payments/momo-verify",
        summary: "Xác thực giao dịch MoMo từ Client",
        description: "API kiểm tra và xử lý kết quả thanh toán MoMo sau khi người dùng được redirect về ứng dụng.",
        operationId: "momoVerify",
        tags: ["Payments"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Thanh toán MoMo thành công.")
                    ]
                )
            )
        ]
    )]
    public function momoVerify(Request $request): JsonResponse
    {
        Log::info('MoMo Verify Client payload: ' . json_encode($request->all()));
        
        $momoService = app(\App\Services\MomoService::class);
        
        if (!$momoService->verifyCallbackSignature($request->all())) {
            return response()->json([
                'success' => false,
                'message' => 'Chữ ký MoMo không hợp lệ.'
            ], 400);
        }
        
        $success = $momoService->processPaymentResult($request->all());
        
        $orderIdWithTime = $request->input('orderId', '');
        $parts = explode('_', $orderIdWithTime);
        $orderId = (int) $parts[0];
        $order = Order::find($orderId);
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Thanh toán MoMo thành công.' : 'Thanh toán MoMo thất bại hoặc bị hủy.',
            'data' => [
                'order_id' => $order ? $order->id : $orderId,
                'total_amount' => $order ? (float)$order->total_amount : $request->input('amount'),
                'payment_method' => $order ? $order->payment_method : 'momo',
                'payment_status' => $order ? $order->payment_status : ($success ? 'paid' : 'failed')
            ]
        ], 200);
    }
}
