<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SellerProductsRequest;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(SellerProductsRequest $request)
    {
        try {
            $user = Auth::user();
            $products = $this->productService->getSellerProducts($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $products->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $product->status->value,
                    'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                    'variants' => $product->variants->map(fn($variant) => [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'variant_name' => $variant->variant_name,
                        'price' => number_format($variant->price, 2, '.', ''),
                        'stock_quantity' => $variant->stock_quantity,
                    ])->toArray(),
                    'images' => $product->images->map(fn($image) => [
                        'id' => $image->id,
                        'image_url' => str_starts_with($image->image, 'http') ? $image->image : asset('storage/' . $image->image),
                    ])->toArray(),
                ])->toArray(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ], 200);

        } catch (Exception $e) {
            $statusCode = $e->getCode() == 400 ? 400 : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống lấy danh sách sản phẩm: ' . $e->getMessage());
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
