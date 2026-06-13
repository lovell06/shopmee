<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use Exception;

class DashboardService
{
    protected const ADMIN_COMMISSION_RATE = 0.05;

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
                
                $startDate = $filters['start_date'] ?? $filters['from_date'] ?? null;
                $endDate = $filters['end_date'] ?? $filters['to_date'] ?? null;

                if (!empty($startDate)) {
                    $q->whereDate('created_at', '>=', $startDate);
                }
                
                if (!empty($endDate)) {
                    $q->whereDate('created_at', '<=', $endDate);
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

    /**
     * Lấy doanh thu thu của Admin trên toàn bộ sàn.
     *
     * @param array $filters
     * @return array
     */
    public function getAdminRevenue(array $filters = []): array
    {
        $baseQuery = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('shops', 'products.shop_id', '=', 'shops.id')
            ->where('orders.status', OrderStatus::Delivered->value);

        $startDate = $filters['start_date'] ?? $filters['from_date'] ?? null;
        $endDate = $filters['end_date'] ?? $filters['to_date'] ?? null;

        if (!empty($startDate)) {
            $baseQuery->whereDate('orders.created_at', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $baseQuery->whereDate('orders.created_at', '<=', $endDate);
        }

        if (!empty($filters['shop_id'])) {
            $baseQuery->where('products.shop_id', $filters['shop_id']);
        }

        $stats = (clone $baseQuery)
            ->selectRaw(
                'COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as total_revenue, COALESCE(SUM(order_items.quantity * order_items.unit_price) * ?, 0) as total_admin_commission, COALESCE(SUM(order_items.quantity), 0) as total_products_sold, COUNT(DISTINCT order_items.order_id) as total_orders_completed',
                [self::ADMIN_COMMISSION_RATE]
            )
            ->first();

        $shops = (clone $baseQuery)
            ->selectRaw(
                'products.shop_id as shop_id, shops.name as shop_name, COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as total_revenue, COALESCE(SUM(order_items.quantity * order_items.unit_price) * ?, 0) as total_admin_commission, COALESCE(SUM(order_items.quantity), 0) as total_products_sold, COUNT(DISTINCT order_items.order_id) as total_orders_completed',
                [self::ADMIN_COMMISSION_RATE]
            )
            ->groupBy('products.shop_id', 'shops.name')
            ->orderByDesc('total_revenue')
            ->get();

        return [
            'total_revenue' => number_format((float)$stats->total_revenue, 2, '.', ''),
            'total_admin_commission' => number_format((float)$stats->total_admin_commission, 2, '.', ''),
            'total_orders_completed' => (int)$stats->total_orders_completed,
            'total_products_sold' => (int)$stats->total_products_sold,
            'currency' => 'VND',
            'commission_rate' => self::ADMIN_COMMISSION_RATE,
            'shops' => $shops->map(fn ($shop) => [
                'shop_id' => $shop->shop_id,
                'shop_name' => $shop->shop_name,
                'total_revenue' => number_format((float)$shop->total_revenue, 2, '.', ''),
                'total_admin_commission' => number_format((float)$shop->total_admin_commission, 2, '.', ''),
                'total_orders_completed' => (int)$shop->total_orders_completed,
                'total_products_sold' => (int)$shop->total_products_sold,
            ])->all(),
        ];
    }
}
