<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Enums\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ProductReviewController extends Controller
{
    /**
     * Store a new product review.
     */
    public function store(Request $request, $orderId, $productId): JsonResponse
    {
        try {
            $userId = Auth::id();

            // 1. Validate inputs
            $validator = Validator::make($request->all(), [
                'rating'  => 'required|integer|between:1,5',
                'comment' => 'nullable|string|max:1000',
                'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // max 2MB
            ], [
                'rating.required' => 'Số sao đánh giá là bắt buộc.',
                'rating.between'  => 'Số sao phải từ 1 đến 5.',
                'image.image'     => 'Tệp tải lên phải là hình ảnh.',
                'image.max'       => 'Dung lượng ảnh không được vượt quá 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()
                ], 422);
            }

            // 2. Find order and verify ownership
            $order = Order::where('id', $orderId)->where('user_id', $userId)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn.'
                ], 404);
            }

            // 3. Verify order status is delivered
            if ($order->status !== OrderStatus::Delivered) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ có thể đánh giá sản phẩm sau khi đơn hàng được giao thành công.'
                ], 400);
            }

            // 4. Verify product belongs to the order
            $orderItem = OrderItem::where('order_id', $orderId)
                ->whereHas('productVariant', function ($query) use ($productId) {
                    $query->where('product_id', $productId);
                })
                ->with('productVariant')
                ->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sản phẩm này không nằm trong đơn hàng của bạn.'
                ], 400);
            }

            // 5. Avoid duplicate reviews
            $exists = ProductReview::where('order_id', $orderId)
                ->where('product_id', $productId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã đánh giá sản phẩm này trong đơn hàng này rồi.'
                ], 400);
            }

            // 6. Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
            }

            // 7. Extract variant name (e.g. "Màu: Đỏ, Size: L")
            $variantName = $orderItem->productVariant->variant_name ?? null;

            // 8. Create product review
            $review = ProductReview::create([
                'user_id'      => $userId,
                'order_id'     => $orderId,
                'product_id'   => $productId,
                'variant_name' => $variantName,
                'rating'       => (int) $request->input('rating'),
                'comment'      => $request->input('comment'),
                'image'        => $imagePath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá sản phẩm thành công!',
                'data'    => [
                    'id'         => $review->id,
                    'rating'     => $review->rating,
                    'comment'    => $review->comment,
                    'image_url'  => $review->image ? asset('storage/' . $review->image) : null,
                    'created_at' => $review->created_at->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Lỗi API lưu đánh giá: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố khi lưu đánh giá. Vui lòng thử lại sau!'
            ], 500);
        }
    }
}
