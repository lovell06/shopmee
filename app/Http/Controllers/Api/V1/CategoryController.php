<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    #[OA\Get(
        path: "/categories",
        summary: "Lấy danh sách danh mục sản phẩm CÔNG KHAI",
        description: "Lấy toàn bộ danh sách danh mục sản phẩm phục vụ cho khách hàng tìm kiếm hoặc lọc.",
        operationId: "getCategories",
        tags: ["Categories"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Lấy danh sách danh mục thành công."),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        try {
            $categories = $this->categoryService->getAllCategories();

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách danh mục thành công.',
                'data'    => CategoryResource::collection($categories)
            ], 200);

        } catch (Exception $e) {
            Log::error('Lỗi lấy danh sách danh mục: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau!'
            ], 500);
        }
    }
}
