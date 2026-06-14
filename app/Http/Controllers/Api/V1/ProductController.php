<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SellerProductsRequest;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    #[OA\Get(
        path: "/seller/products",
        summary: "Lấy danh sách sản phẩm của Shop (dành cho Seller)",
        description: "Lấy danh sách các sản phẩm thuộc về cửa hàng của Seller đang đăng nhập.",
        operationId: "getSellerProducts",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", description: "Tìm kiếm theo tên sản phẩm hoặc SKU biến thể", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", description: "Lọc theo trạng thái sản phẩm", required: false, schema: new OA\Schema(type: "string", enum: ["active", "pending", "hidden"]))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Seller chưa đăng ký cửa hàng",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Bạn chưa đăng ký cửa hàng.")
                    ]
                )
            )
        ]
    )]
    public function index(SellerProductsRequest $request)
    {
        try {
            $user = Auth::user();

            /** @var \Illuminate\Pagination\LengthAwarePaginator $products */
            $products = $this->productService->getSellerProducts($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $products->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category_id' => $product->category_id,
                    'description' => $product->description,
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

    #[OA\Post(
        path: "/products",
        summary: "Đăng sản phẩm mới và tạo các biến thể",
        description: "API dành cho Seller để đăng sản phẩm mới kèm các biến thể và ảnh tùy chọn.",
        operationId: "storeProduct",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["category_id", "name", "description", "variants"],
                    properties: [
                        new OA\Property(property: "category_id", type: "integer", example: 1),
                        new OA\Property(property: "name", type: "string", example: "Áo thun Polo Classic"),
                        new OA\Property(property: "description", type: "string", example: "Mô tả chi tiết Áo thun Polo Classic"),
                        new OA\Property(
                            property: "variants",
                            type: "array",
                            items: new OA\Items(
                                required: ["sku", "variant_name", "price", "stock_quantity"],
                                properties: [
                                    new OA\Property(property: "sku", type: "string", example: "POLO-W-M"),
                                    new OA\Property(property: "variant_name", type: "string", example: "White - M"),
                                    new OA\Property(property: "price", type: "number", format: "float", example: 199000),
                                    new OA\Property(property: "stock_quantity", type: "integer", example: 50)
                                ]
                            )
                        ),
                        new OA\Property(
                            property: "images",
                            type: "array",
                            items: new OA\Items(type: "string", format: "binary"),
                            description: "Mảng các file ảnh upload"
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Tạo sản phẩm thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đăng sản phẩm và tạo các biến thể thành công"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "name", type: "string", example: "Áo thun Polo Classic"),
                                new OA\Property(property: "variants_count", type: "integer", example: 2)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Yêu cầu không hợp lệ hoặc lỗi nghiệp vụ",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Bạn chưa đăng ký cửa hàng.")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Chưa xác thực",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Unauthenticated.")
                    ]
                )
            )
        ]
    )]
    public function store(StoreProductRequest $request)
    {
        try {
            $user = Auth::user();

            $product = $this->productService->createProduct($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Đăng sản phẩm và tạo các biến thể thành công',
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'variants_count' => $product->variants()->count()
                ]
            ], 201);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống đăng sản phẩm: ' . $e->getMessage());
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

    #[OA\Put(
        path: "/seller/products/{id}",
        summary: "Cập nhật thông tin sản phẩm và biến thể",
        description: "Cập nhật thông tin chi tiết sản phẩm và danh sách các biến thể của nó (yêu cầu đi kèm ID của biến thể).",
        operationId: "updateProduct",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của sản phẩm cần cập nhật", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category_id", "name", "description", "variants"],
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "name", type: "string", example: "Áo thun Polo Classic Updated"),
                    new OA\Property(property: "description", type: "string", example: "Mô tả cập nhật..."),
                    new OA\Property(
                        property: "variants",
                        type: "array",
                        items: new OA\Items(
                            required: ["id", "sku", "variant_name", "price", "stock_quantity"],
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "sku", type: "string", example: "POLO-W-M-NEW"),
                                new OA\Property(property: "variant_name", type: "string", example: "White - M - Updated"),
                                new OA\Property(property: "price", type: "number", format: "float", example: 219000),
                                new OA\Property(property: "stock_quantity", type: "integer", example: 60)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Cập nhật thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Cập nhật thông tin sản phẩm và biến thể thành công"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),
                                new OA\Property(property: "updated_at", type: "string", example: "2026-06-09 22:50:00")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Dữ liệu không hợp lệ",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Biến thể không hợp lệ cho sản phẩm này.")
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Không có quyền truy cập sản phẩm này",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Bạn không có quyền chỉnh sửa sản phẩm này")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Không tìm thấy sản phẩm",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Sản phẩm không tồn tại.")
                    ]
                )
            )
        ]
    )]
    public function update(UpdateProductRequest $request, $id)
    {
        try {
            $user = Auth::user();

            $product = $this->productService->updateProduct($user->id, (int)$id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thông tin sản phẩm và biến thể thành công',
                'data' => [
                    'product_id' => $product->id,
                    'updated_at' => $product->updated_at->format('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống cập nhật sản phẩm: ' . $e->getMessage());
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

    #[OA\Delete(
        path: "/seller/products/{id}",
        summary: "Xóa sản phẩm (Soft Delete)",
        description: "Xóa sản phẩm thuộc về Shop của Seller đang đăng nhập (sử dụng soft delete).",
        operationId: "deleteProduct",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của sản phẩm cần xóa", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Xóa thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Xóa sản phẩm thành công")
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Không có quyền",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Bạn không có quyền xóa sản phẩm này")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Không tìm thấy",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Sản phẩm không tồn tại.")
                    ]
                )
            )
        ]
    )]
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            $this->productService->deleteProduct($user->id, (int)$id);

            return response()->json([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công'
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống xóa sản phẩm: ' . $e->getMessage());
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

