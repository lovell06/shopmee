<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RevenueRequest;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class SellerDashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    #[OA\Get(
        path: "/seller/dashboard/revenue",
        summary: "Xem tổng doanh thu và thống kê của Shop",
        description: "API thống kê doanh thu theo mốc thời gian dành cho Seller.",
        operationId: "getSellerRevenue",
        tags: ["Seller Dashboard"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", description: "Ngày bắt đầu (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Ngày kết thúc (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "from_date", in: "query", description: "Ngày bắt đầu thay thế (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "to_date", in: "query", description: "Ngày kết thúc thay thế (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "total_revenue", type: "number", format: "float", example: 1548000),
                                new OA\Property(property: "orders_count", type: "integer", example: 12)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: "Tài khoản không phải Seller hoặc chưa đăng ký Shop"
            )
        ]
    )]
    public function revenue(RevenueRequest $request)
    {
        try {
            $user = Auth::user();

            $data = $this->dashboardService->getSellerRevenue($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống lấy doanh thu seller: ' . $e->getMessage());
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
