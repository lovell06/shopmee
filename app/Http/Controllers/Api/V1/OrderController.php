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
                        'payment_method' => $order->payment_method, // Hoặc $order->payment_method->value nếu có cast
                        'qr_simulation'  => "Vui lòng giả lập gửi tiền số tiền " . number_format($order->total_amount) . "đ với nội dung: SHOPMEE " . $order->id
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
}
