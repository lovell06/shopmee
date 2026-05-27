<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RevenueRequest;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class SellerDashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Xem tổng doanh thu và thống kê Shop
     */
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
