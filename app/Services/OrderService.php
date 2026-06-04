<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\DB;
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
        $order = Order::query()->find($orderId);
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

    /**
     * Xử lý đặt hàng (Checkout) dựa trên cấu trúc DB của bạn
     */
    public function processCheckout(string $userId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $data) {
            // 1. Tìm giỏ hàng hiện tại của người dùng
            $cart = Cart::query()->where('user_id', $userId)->with('items.productVariant')->first();
            if (!$cart || $cart->items->isEmpty()) {
                throw new Exception('Không thể thanh toán do giỏ hàng của bạn đang trống.', 400);
            }

            $totalAmount = 0;
            $itemsToInsert = [];

            // 2. Duyệt giỏ hàng kiểm tra tồn kho và gom tiền
            foreach ($cart->items as $item) {
                $variant = $item->productVariant;

                if ($variant->stock_quantity < $item->quantity) {
                    throw new Exception("Sản phẩm '{$variant->variant_name}' trong kho không đủ số lượng đáp ứng.", 400);
                }

                // unit_price khớp với cột decimal(18,2) trong migration order_items của bạn
                $itemSubtotal = $variant->price * $item->quantity; 
                $totalAmount += $itemSubtotal;

                $itemsToInsert[] = [
                    'product_variant_id' => $variant->id,
                    'quantity'           => $item->quantity,
                    'unit_price'         => $variant->price,
                    'variant_model'      => $variant
                ];
            }

            // 3. Tạo đơn hàng lưu vào bảng orders
            $order = Order::query()->create([
                'user_id'         => $userId,
                'user_address_id' => $data['user_address_id'],
                'total_amount'    => $totalAmount,
                'description'     => $data['description'] ?? null,
                'status'          => OrderStatus::Pending->value,        // Sử dụng giá trị Enum của bạn
                'payment_status'  => PaymentStatus::Pending->value,
                'payment_method'  => $data['payment_method'],
            ]);

            // 4. Tạo chi tiết đơn hàng (order_items) và trừ kho hàng
            foreach ($itemsToInsert as $itemData) {
                OrderItem::query()->create([
                    'order_id'           => $order->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'quantity'           => $itemData['quantity'],
                    'unit_price'         => $itemData['unit_price'], 
                    'description'        => null
                ]);

                // Tiến hành trừ kho sản phẩm
                $itemData['variant_model']->decrement('stock_quantity', $itemData['quantity'], []);
            }

            // 5. Xóa sạch giỏ hàng sau khi checkout thành công
            CartItem::query()->where('cart_id', $cart->id)->delete();

            return $order;
        });
    }

    /**
     * GIẢ LẬP THANH TOÁN CHUYỂN KHOẢN
     */
    public function simulatePaymentSuccess(int $orderId, string $userId): bool
    {
        $order = Order::query()->where('id', $orderId)->where('user_id', $userId)->first();
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng cần thanh toán.', 404);
        }

        // Kiểm tra nếu đơn hàng gửi lên không phải cấu hình là chuyển khoản ngân hàng ngân hàng
        if ($order->payment_method === PaymentMethod::CashOnDelivery->value) { 
            throw new Exception('Đơn hàng này thanh toán bằng tiền mặt COD, không thể giả lập chuyển khoản.', 400);
        }
    
        if ($order->payment_status === PaymentStatus::Paid->value) { 
            throw new Exception('Đơn hàng này đã được xác nhận thanh toán rồi.', 400);
        }
    
        $order->update([
            'payment_status' => PaymentStatus::Paid->value,
            'status'         => OrderStatus::Pending->value
        ]);
    
        return true;
    }

    /**
     * Lấy danh sách lịch sử đơn hàng của User
     */
    public function getUserOrderHistory(string $userId)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with(['items.productVariant.product']) // Eager load để lấy tên/ảnh sản phẩm ở Frontend
            ->orderByDesc('created_at') // Đơn hàng mới nhất xếp lên đầu
            ->get();
    }

    /**
     * Xử lý hủy đơn hàng (Chỉ cho phép khi ở trạng thái Pending)
     */
    public function cancelOrder(int $orderId, string $userId): bool
    {
        return DB::transaction(function () use ($orderId, $userId) {
            // 1. Tìm đơn hàng hợp lệ của chính User đó
            $order = Order::query()->where('id', $orderId)->where('user_id', $userId)->first();
            if (!$order) {
                throw new Exception('Không tìm thấy đơn hàng yêu cầu.', 404);
            }

            // 2. CHỐT CHẶN NGHIỆP VỤ: Chỉ cho phép hủy khi trạng thái là Pending
            if ($order->status !== OrderStatus::Pending) {
                throw new Exception('Đơn hàng đã được xử lý hoặc đã hủy, không thể thực hiện hủy lúc này.', 400);
            }

            // 3. HOÀN KHO: Duyệt qua tất cả món hàng trong đơn để cộng trả lại kho sản phẩm
            $orderItems = OrderItem::query()->where('order_id', $order->id)->with('productVariant')->get();
            foreach ($orderItems as $item) {
                $variant = $item->productVariant;
                if ($variant) {
                    $variant->stock_quantity += $item->quantity;
                    $variant->save();
                }
            }

            // 4. Cập nhật trạng thái đơn hàng thành Cancelled (Hủy)
            $order->update([
                'status' => OrderStatus::Cancelled->value,
            ]);

            return true;
        });
    }
}
