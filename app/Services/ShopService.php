<?php

namespace App\Services;

use App\Models\Shop;
use Exception;

class ShopService
{
    /**
     * Xử lý nghiệp vụ đăng ký mở Shop
     */
    public function registerShop(array $data, string $userId): Shop
    {
        // 1. Kiểm tra logic xem user đã có shop chưa
        $existsShop = Shop::query()->where('owner_id', $userId)->first();
        if ($existsShop) {
            // Quăng ra một Exception hoặc dùng Exception mặc định kèm thông báo lỗi
            throw new Exception('Bạn đã đăng ký hoặc đã sở hữu một cửa hàng.', 400);
        }

        // 2. Lưu ảnh logo từ máy lên thư mục storage/logos
        $logoPath = null;
        if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $logoPath = $data['logo']->store('logos', 'public');
        }

        // 3. Thực hiện tạo Shop trong Database
        return Shop::create([
            'owner_id'     => $userId,
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'logo_url'     => $logoPath,
        ]);
    }
}