<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use Exception;

class DashboardService
{
    /**
     * Lấy thống kê doanh thu của Shop
     *
     * @param string $userId
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function getSellerRevenue(string $userId, array $filters): array
    {
        // 1. Kiểm tra shop của user
        $shop = Shop::query()->where('owner_id', $userId)->first();
        if (!$shop) {
            throw new Exception('Bạn chưa đăng ký cửa hàng.', 400);
        }

        // 2. Xây dựng truy vấn các OrderItem thuộc về Shop này và có Order status là delivered
        $query = OrderItem::query()
            ->whereHas('productVariant.product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })
            ->whereHas('order', function ($q) use ($filters) {
                $q->where('status', OrderStatus::Delivered->value);
                
                if (!empty($filters['start_date'])) {
                    $q->whereDate('created_at', '>=', $filters['start_date']);
                }
                
                if (!empty($filters['end_date'])) {
                    $q->whereDate('created_at', '<=', $filters['end_date']);
                }
            });

        // 3. Tính toán các chỉ số thống kê
        $stats = $query->selectRaw('
            COALESCE(SUM(quantity * unit_price), 0) as total_revenue,
            COALESCE(SUM(quantity), 0) as total_products_sold,
            COUNT(DISTINCT order_id) as total_orders_completed
        ')->first();

        return [
            'total_revenue' => number_format((float)$stats->total_revenue, 2, '.', ''),
            'total_orders_completed' => (int)$stats->total_orders_completed,
            'total_products_sold' => (int)$stats->total_products_sold,
            'currency' => 'VND',
        ];
    }
}
