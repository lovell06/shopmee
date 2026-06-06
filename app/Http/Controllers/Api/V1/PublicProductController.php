<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductListRequest; 
use App\Http\Resources\Api\V1\ProductResource;   
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Product;
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

    /**
     * Xem thông tin chi tiết một sản phẩm theo ID (API công khai)
     * URL: GET /api/v1/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProductById($id);

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết sản phẩm thành công.',
                'data'    => new ProductDetailResource($product)
            ], 200);

        } catch (Exception $e) {
            $statusCode = $e->getCode() === 404 ? 404 : 500;
            if ($statusCode === 500) {
                Log::error("Lỗi lấy chi tiết sản phẩm #{$id}: " . $e->getMessage());
            }
            return response()->json([
                'success' => false,
                'message' => $statusCode === 404 ? $e->getMessage() : 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau!'
            ], $statusCode);
        }
    }

    /**
     * Tìm kiếm sản phẩm công khai (Dành cho tất cả role)
     * URL: GET /api/v1/products/search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->query('q', '');

            // Khởi tạo truy vấn tìm kiếm các sản phẩm đang Active (Hoạt động)
            $query = Product::query()
                ->where('status', \App\Enums\ProductStatus::Active)
                ->with(['variants', 'images']);

            // Nếu người dùng có truyền từ khóa thì mới kích hoạt bộ lọc LIKE
            if ($searchTerm !== '') {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('description', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhereHas('variants', function ($qv) use ($searchTerm) {
                          $qv->where('sku', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('variant_name', 'LIKE', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('category', function ($qc) use ($searchTerm) {
                          $qc->where('name', 'LIKE', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('shop', function ($qs) use ($searchTerm) {
                          $qs->where('name', 'LIKE', '%' . $searchTerm . '%');
                      });
                });
            }

            $limit = $request->query('limit', 15);
            $products = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'message' => 'Tìm kiếm sản phẩm thành công.',
                'data'    => ProductResource::collection($products)->response()->getData(true)
            ], 200);

        } catch (Exception $e) {
            Log::error('Lỗi khi tìm kiếm sản phẩm: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau!'
            ], 500);
        }
    }
}
