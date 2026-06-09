<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Xử lý thêm sản phẩm vào giỏ hàng
     */
    public function addToCart(string $userId, array $data)
    {
        $variantId = $data['product_variant_id'];
        $quantity  = $data['quantity'];

        // 1. Kiểm tra kho hàng của biến thể sản phẩm
        $variant = ProductVariant::query()->find($variantId);
        if (!$variant) {
            throw new Exception('Biến thể sản phẩm không tồn tại.', 404);
        }

        if ($variant->stock_quantity < $quantity) {
            throw new Exception("Sản phẩm này chỉ còn {$variant->stock_quantity} cái trong kho, không đủ đáp ứng.", 400);
        }

        // 2. Chạy Transaction để đảm bảo an toàn dữ liệu giữa bảng carts và cart_items
        return DB::transaction(function () use ($userId, $variantId, $quantity, $variant) {
            
            // Tìm giỏ hàng hiện tại của User, nếu chưa có thì tạo mới luôn
            $cart = Cart::query()->firstOrCreate([
                'user_id' => $userId
            ]);

            // 3. Kiểm tra xem biến thể này đã có trong giỏ hàng (cart_items) của họ chưa
            $cartItem = DB::table('cart_items')
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $variantId)
                ->first();

            if ($cartItem) {
                // Tình huống đã có: Tính tổng số lượng mới xem có vượt kho không
                $newQuantity = $cartItem->quantity + $quantity;
                if ($variant->stock_quantity < $newQuantity) {
                    throw new Exception("Tổng số lượng trong giỏ hàng ({$newQuantity}) vượt quá số lượng kho hiện có.", 400);
                }

                // Cập nhật tăng số lượng
                DB::table('cart_items')
                    ->where('id', $cartItem->id)
                    ->update([
                        'quantity'   => $newQuantity
                    ]);
            } else {
                // Tình huống chưa có: Tạo mới một dòng item trong giỏ hàng
                DB::table('cart_items')->insert([
                    'cart_id'            => $cart->id,
                    'product_variant_id' => $variantId,
                    'quantity'           => $quantity,
                ]);
            }

            return true;
        });
    }

    /**
    * Lấy danh sách giỏ hàng và tự động gom nhóm theo từng Shop 
    */
    public function getCartGroupedByShop(string $userId): array
    {
        // 1. Lấy giỏ hàng của User kèm theo toàn bộ mối quan hệ lồng nhau để né lỗi N+1
        // Giả định trong Model Cart bạn có quan hệ hasMany tên là 'items'
        $cart = \App\Models\Cart::query()
            ->where('user_id', $userId)
            ->with([
                'items.productVariant.product.shop',
                'items.productVariant.product.images'
            ])
            ->first();

        // Nếu người dùng chưa từng có giỏ hàng hoặc giỏ hàng trống, trả về mảng rỗng
        if (!$cart || $cart->items->isEmpty()) {
            return [];
        }

        // 2. Thuật toán gom nhóm các mặt hàng theo Shop ID của sản phẩm
        $groupedByShop = $cart->items->groupBy(function ($item) {
            return $item->productVariant->product->shop_id;
        });

        $result = [];

        // 3. Duyệt qua từng nhóm Shop để định dạng lại cấu trúc JSON phân tầng
        foreach ($groupedByShop as $shopId => $cartItems) {
            // Lấy thông tin Shop đại diện từ item đầu tiên trong nhóm
            $shop = $cartItems->first()->productVariant->product->shop;

            $result[] = [
                'shop_id'   => $shop->id,
                'shop_name' => $shop->name,
                // Danh sách các sản phẩm thuộc về Shop này trong giỏ hàng
                'items'     => $cartItems->map(function ($item) {
                    $variant = $item->productVariant;
                    $product = $variant->product;
                    $image   = $product->images->first(); // Lấy ảnh đầu tiên làm ảnh đại diện

                    return [
                        'cart_item_id'   => $item->id,
                        'product_id'     => $product->id,
                        'product_name'   => $product->name,
                        'variant_id'     => $variant->id,
                        'variant_name'   => $variant->variant_name,
                        'sku'            => $variant->sku,
                        'price'          => (float) $variant->price,
                        'quantity'       => (int) $item->quantity,
                        'stock_quantity' => (int) $variant->stock_quantity,
                        // Tự động kiểm tra trạng thái còn hàng hay hết hàng cho Frontend hiển thị
                        'is_available'   => $variant->stock_quantity >= $item->quantity,
                        'image_url'      => $image 
                            ? (str_starts_with($image->image, 'http') ? $image->image : asset('storage/' . $image->image))
                            : null,
                    ];
                })->toArray()
            ];
        }

        return $result;
    }

    /**
     * Cập nhật số lượng sản phẩm trong giỏ hàng
     */
    public function updateCartItemQuantity(string $userId, int $cartItemId, int $quantity): bool
    {
        // 1. Tìm CartItem kèm theo Cart để xác thực quyền sở hữu
        $cartItem = CartItem::query()
            ->where('id', $cartItemId)
            ->whereHas('cart', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->first();

        if (!$cartItem) {
            throw new Exception('Sản phẩm không tồn tại trong giỏ hàng của bạn.', 404);
        }

        // 2. Kiểm tra tồn kho của biến thể sản phẩm
        $variant = ProductVariant::query()->find($cartItem->product_variant_id);
        if (!$variant) {
            throw new Exception('Biến thể sản phẩm không tồn tại.', 404);
        }

        if ($variant->stock_quantity < $quantity) {
            throw new Exception("Sản phẩm này chỉ còn {$variant->stock_quantity} cái trong kho, không đủ đáp ứng.", 400);
        }

        // 3. Cập nhật số lượng
        $cartItem->quantity = $quantity;
        return $cartItem->save();
    }

    /**
     * Xóa sản phẩm khỏi giỏ hàng
     */
    public function removeCartItem(string $userId, int $cartItemId): bool
    {
        $cartItem = CartItem::query()
            ->where('id', $cartItemId)
            ->whereHas('cart', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->first();

        if (!$cartItem) {
            throw new Exception('Sản phẩm không tồn tại trong giỏ hàng của bạn.', 404);
        }

        return $cartItem->delete();
    }

    /**
     * Xóa hàng loạt sản phẩm khỏi giỏ hàng
     */
    public function bulkRemoveCartItems(string $userId, array $cartItemIds): bool
    {
        // Tìm các cart items hợp lệ thuộc giỏ hàng của user này
        $cartItems = CartItem::query()
            ->whereIn('id', $cartItemIds)
            ->whereHas('cart', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get();

        if ($cartItems->isEmpty()) {
            throw new Exception('Không tìm thấy các sản phẩm hợp lệ trong giỏ hàng của bạn để xóa.', 404);
        }

        // Thực hiện xóa
        return CartItem::destroy($cartItems->pluck('id')) > 0;
    }

    /**
     * Lấy tổng số lượng sản phẩm trong giỏ hàng (Badge Count)
     */
    public function getCartCount(string $userId): int
    {
        return (int) DB::table('cart_items')
            ->join('carts', 'cart_items.cart_id', '=', 'carts.id')
            ->where('carts.user_id', $userId)
            ->sum('cart_items.quantity');
    }
}