<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductListRequest; 
use App\Http\Resources\Api\V1\ProductResource;   
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class PublicProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Xem danh sách sản phẩm CÔNG KHAI (Dành cho khách vãng lai / người mua)
     * URL: GET /api/v1/products
     */
    public function index(ProductListRequest $request): JsonResponse
    {
        try {
            // Lấy toàn bộ tham số search, filter đã qua validate
            $filters = $request->validated();

            // Gọi service xử lý truy vấn phân trang sản phẩm chung
            $products = $this->productService->getProductList($filters);

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách sản phẩm thành công.',
                'data'    => ProductResource::collection($products)->response()->getData(true)
            ], 200);

        } catch (Exception $e) {
            Log::error('Lỗi lấy danh sách sản phẩm công khai: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau!'
            ], 500);
        }
    }
}
