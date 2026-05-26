<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class ProductService
{
    /**
     * Lấy danh sách sản phẩm kèm biến thể và ảnh của Shop thuộc User
     *
     * @param string $userId
     * @param array $filters
     * @return LengthAwarePaginator
     * @throws Exception
     */
    public function getSellerProducts(string $userId, array $filters): LengthAwarePaginator
    {
        // 1. Kiểm tra shop của user
        $shop = Shop::query()->where('owner_id', $userId)->first();
        if (!$shop) {
            throw new Exception('Bạn chưa đăng ký cửa hàng.', 400);
        }

        // 2. Xây dựng query lấy sản phẩm thuộc shop
        $query = Product::query()
            ->where('shop_id', $shop->id)
            ->with(['variants', 'images']);

        // 3. Lọc theo trạng thái (status)
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 4. Tìm kiếm theo tên hoặc SKU
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('variants', function ($qv) use ($search) {
                      $qv->where('sku', 'like', '%' . $search . '%');
                  });
            });
        }

        // 5. Phân trang kết quả
        return $query->orderBy('created_at', 'desc')->paginate(10);
    }
}
