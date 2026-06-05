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

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * API Checkout đặt hàng chính thức
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            // 1. Lấy ra mảng dữ liệu đã qua bộ lọc validate của CheckoutRequest
            $validatedData = $request->validated();
            
            // 2. Tiến hành gọi dịch vụ xử lý đặt hàng
            $order = $this->orderService->processCheckout($userId, $validatedData);
        
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
                try {
                    $momoService = app(\App\Services\MomoService::class);
                    $payUrl = $momoService->createPaymentUrl($order);

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
                } catch (Exception $e) {
                    Log::error('Lỗi khi tạo giao dịch MoMo cho đơn hàng #' . $order->id . ': ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể kết nối tới cổng thanh toán MoMo: ' . $e->getMessage(),
                        'order_id' => $order->id
                    ], 400);
                }
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

    /**
     * API Webhook Giả lập cổng thanh toán online báo thành công
     */
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

    public function checkStatus(int $id): JsonResponse
    {
        $order = Order::query()->where('id', $id)->where('user_id', Auth::id())->first();
        
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
        }

        return response()->json([
            'success' => true,
            'payment_status' => $order->payment_status, // Trả về 'pending' hoặc 'paid'
            'status' => $order->status
        ], 200);
    }

    /**
     * API Xem lịch sử đặt hàng
     */
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

    /**
     * API Hủy đơn hàng
     */
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

    /**
     * API Webhook nhận thông báo IPN từ MoMo
     */
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

    /**
     * API Xác thực giao dịch MoMo từ phía client sau khi redirect
     */
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
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Thanh toán MoMo thành công.' : 'Thanh toán MoMo thất bại hoặc bị hủy.',
            'data' => $request->all()
        ], 200);
    }
}
