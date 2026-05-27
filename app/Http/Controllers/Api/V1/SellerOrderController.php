<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class SellerOrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Cập nhật trạng thái đơn hàng của shop
     */
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
}
