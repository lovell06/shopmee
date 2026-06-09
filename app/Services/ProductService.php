<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
    
     /**
     * Lấy danh sách sản phẩm nâng cao (Search, Filter, Sort, Paginate)
     */
    public function getProductList(array $filters): LengthAwarePaginator
    {
        // Khởi tạo Query, luôn đi kèm dữ liệu variants và images
        $query = Product::query()->with(['variants', 'images']); 

        // 1. TÌM KIẾM: Theo tên sản phẩm
        if (!empty($filters['search'])) {
            $query->where('products.name', 'LIKE', '%' . $filters['search'] . '%');
        }

        // 2. BỘ LỌC KHOẢNG GIÁ: Tìm xem sản phẩm nào có biến thể nằm trong khoảng giá
        if (isset($filters['price_min']) || isset($filters['price_max'])) {
            $query->whereHas('variants', function ($q) use ($filters) {
                if (isset($filters['price_min'])) {
                    $q->where('price', '>=', $filters['price_min']);
                }
                if (isset($filters['price_max'])) {
                    $q->where('price', '<=', $filters['price_max']);
                }
            });
        }

        // 3. SẮP XẾP NÂNG CAO: Kỹ thuật Subquery
        if (($filters['sort_by'] ?? '') === 'price') {
            $sortDir = $filters['sort_dir'] ?? 'desc';
            
            // Tạo một cột ảo 'target_price' lấy ra giá của biến thể đại diện để sắp xếp
            $query->addSelect(['target_price' => DB::table('product_variants')
                ->select('price')
                ->whereColumn('product_id', 'products.id')
                ->orderBy('price', $sortDir)
                ->limit(1)
            ])->orderBy('target_price', $sortDir);
        } else {
            // Mặc định sắp xếp theo ngày tạo mới nhất của sản phẩm
            $sortBy  = $filters['sort_by'] ?? 'created_at';
            $sortDir = $filters['sort_dir'] ?? 'desc';
            $query->orderBy('products.' . $sortBy, $sortDir);
        }

        $limit = $filters['limit'] ?? 15;
        
        return $query->paginate($limit);
    }
    
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

    /**
     * Đăng sản phẩm mới và tạo các biến thể
     *
     * @param string $userId
     * @param array $data
     * @return Product
     * @throws Exception
     */
    public function createProduct(string $userId, array $data): Product
    {
        // 1. Kiểm tra shop của user
        $shop = Shop::query()->where('owner_id', $userId)->first();
        if (!$shop) {
            throw new Exception('Bạn chưa đăng ký cửa hàng.', 400);
        }

        // 2. Thực hiện tạo sản phẩm và biến thể trong database transaction
        return DB::transaction(function () use ($shop, $data) {
            $product = Product::create([
                'shop_id' => $shop->id,
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'status' => \App\Enums\ProductStatus::Active, // Đăng sản phẩm thì hoạt động ngay
            ]);

            foreach ($data['variants'] as $variantData) {
                $product->variants()->create([
                    'sku' => $variantData['sku'],
                    'variant_name' => $variantData['variant_name'],
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                ]);
            }

            if (!empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imageFile) {
                    if ($imageFile instanceof \Illuminate\Http\UploadedFile) {
                        $path = $imageFile->store('products', 'public');
                        $product->images()->create([
                            'image' => $path,
                        ]);
                    }
                }
            }

            return $product;
        });
    }

    /**
     * Cập nhật thông tin sản phẩm và các biến thể
     *
     * @param string $userId
     * @param int $productId
     * @param array $data
     * @return Product
     * @throws Exception
     */
    public function updateProduct(string $userId, int $productId, array $data): Product
    {
        // 1. Kiểm tra shop của user
        $shop = Shop::query()->where('owner_id', $userId)->first();
        if (!$shop) {
            throw new Exception('Bạn chưa đăng ký cửa hàng.', 400);
        }

        // 2. Tìm sản phẩm
        
        $product = Product::query()->find($productId);
        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại.', 404);
        }

        // 3. Kiểm tra xem sản phẩm có thuộc về Shop của user hay không
        if ($product->shop_id !== $shop->id) {
            throw new Exception('Bạn không có quyền chỉnh sửa sản phẩm này', 403);
        }

        // 4. Kiểm tra xem tất cả các variant id truyền lên có thuộc về sản phẩm này hay không
        $productVariantIds = $product->variants()->pluck('id')->toArray();
        foreach ($data['variants'] as $variantData) {
            if (!in_array($variantData['id'], $productVariantIds)) {
                throw new Exception('Biến thể không hợp lệ cho sản phẩm này.', 400);
            }
        }

        // 5. Thực hiện cập nhật trong database transaction
        return DB::transaction(function () use ($product, $data) {
            $product->update([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'],
            ]);

            foreach ($data['variants'] as $variantData) {
                $product->variants()->where('id', $variantData['id'])->update([
                    'sku' => $variantData['sku'],
                    'variant_name' => $variantData['variant_name'],
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                ]);
            }

            return $product->fresh();
        });
    }

    /**
     * Soft delete a sản phẩm
     *
     * @param string $userId
     * @param int $productId
     * @return bool
     * @throws Exception
     */
    public function deleteProduct(string $userId, int $productId): bool
    {
        // 1. Kiểm tra shop của user
        $shop = Shop::query()->where('owner_id', $userId)->first();
        if (!$shop) {
            throw new Exception('Bạn chưa đăng ký cửa hàng.', 400);
        }

        // 2. Tìm sản phẩm
        $product = Product::query()->find($productId);
        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại.', 404);
        }

        // 3. Kiểm tra xem sản phẩm có thuộc về Shop của user hay không
        if ($product->shop_id !== $shop->id) {
            throw new Exception('Bạn không có quyền xóa sản phẩm này', 403);
        }

        // 4. Thực hiện soft delete
        // return $product->delete();
        $action = 'delete';
        return $product->$action();
    }

    /**
     * Lấy thông tin chi tiết một sản phẩm theo ID (dành cho API công khai)
     *
     * @param int $id
     * @return Product
     * @throws Exception
     */
    public function getProductById(int $id): Product
    {
        $product = Product::query()
            ->with(['variants', 'images', 'shop', 'category'])
            ->find($id);

        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại.', 404);
        }

        return $product;
    }
}

