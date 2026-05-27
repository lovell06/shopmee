<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use Exception;

class OrderService
{
    /**
     * Cập nhật trạng thái đơn hàng của Shop
     *
     * @param string $userId
     * @param int $orderId
     * @param string $status
     * @return Order
     * @throws Exception
     */
    public function updateOrderStatus(string $userId, int $orderId, string $status): Order
    {
        // 1. Kiểm tra shop của user
        $shop = Shop::query()->where('owner_id', $userId)->first();
        if (!$shop) {
            throw new Exception('Bạn chưa đăng ký cửa hàng.', 400);
        }

        // 2. Tìm đơn hàng
        $order = Order::find($orderId);
        if (!$order) {
            throw new Exception('Đơn hàng không tồn tại.', 404);
        }

        // 3. Kiểm tra xem đơn hàng có chứa sản phẩm thuộc về Shop của user hay không
        $hasShopItem = $order->items()
            ->whereHas('productVariant.product', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
            ->exists();

        if (!$hasShopItem) {
            throw new Exception('Bạn không có quyền cập nhật đơn hàng này.', 403);
        }

        // 4. Cập nhật trạng thái đơn hàng
        $order->status = $status;
        $order->save();

        return $order;
    }
}
