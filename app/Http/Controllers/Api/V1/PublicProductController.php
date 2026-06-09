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
use OpenApi\Attributes as OA;

class PublicProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    #[OA\Get(
        path: "/products",
        summary: "Xem danh sách sản phẩm CÔNG KHAI",
        description: "Lấy danh sách sản phẩm công khai hỗ trợ tìm kiếm, lọc theo giá, lọc theo danh mục, sắp xếp và phân trang.",
        operationId: "getPublicProducts",
        tags: ["Public Products"],
        parameters: [
            new OA\Parameter(name: "search", in: "query", description: "Từ khóa tìm kiếm theo tên sản phẩm", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "price_min", in: "query", description: "Giá tối thiểu", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "price_max", in: "query", description: "Giá tối đa", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "sort_by", in: "query", description: "Trường sắp xếp", required: false, schema: new OA\Schema(type: "string", enum: ["created_at", "price"])),
            new OA\Parameter(name: "sort_dir", in: "query", description: "Hướng sắp xếp", required: false, schema: new OA\Schema(type: "string", enum: ["asc", "desc"])),
            new OA\Parameter(name: "limit", in: "query", description: "Số lượng sản phẩm mỗi trang", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Lấy danh sách sản phẩm thành công."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: "/products/{id}",
        summary: "Xem chi tiết một sản phẩm theo ID",
        description: "Lấy thông tin chi tiết của sản phẩm kèm các biến thể và danh sách hình ảnh.",
        operationId: "getPublicProductById",
        tags: ["Public Products"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của sản phẩm", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Lấy chi tiết sản phẩm thành công."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Sản phẩm không tồn tại",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Sản phẩm không tồn tại.")
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: "/products/search",
        summary: "Tìm kiếm sản phẩm nâng cao",
        description: "Tìm kiếm sản phẩm theo tên, mô tả, biến thể, danh mục hoặc cửa hàng.",
        operationId: "searchPublicProducts",
        tags: ["Public Products"],
        parameters: [
            new OA\Parameter(name: "q", in: "query", description: "Từ khóa tìm kiếm", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "limit", in: "query", description: "Số lượng sản phẩm mỗi trang", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Tìm kiếm sản phẩm thành công."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
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
