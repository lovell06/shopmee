<?php

namespace App\Services;

use App\Models\UserAddress; 
use Illuminate\Support\Facades\DB;
use Exception;

class AddressService
{
    /**
     * Lấy toàn bộ danh sách địa chỉ của User
     */
    public function getAddresses(string $userId)
    {
        return UserAddress::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default') 
            ->orderByDesc('created_at') 
            ->get();
    }

    /**
     * Thêm địa chỉ mới vào hệ thống
     */
    public function createAddress(string $userId, array $data): bool
    {
        return DB::transaction(function () use ($userId, $data) {
            $isDefault = $data['is_default'] ?? false;

            // Nếu đây là địa chỉ đầu tiên của tài khoản này, bắt buộc ép làm mặc định
            $hasAddress = UserAddress::query()->where('user_id', $userId)->exists();
            if (!$hasAddress) {
                $isDefault = true;
            }

            // Nếu địa chỉ mới thiết lập làm mặc định, hạ toàn bộ địa chỉ cũ của user này xuống false
            if ($isDefault) {
                UserAddress::query()->where('user_id', $userId)->update(['is_default' => false]);
            }

            UserAddress::query()->create([
                'user_id'          => $userId,
                'receiver_name'    => $data['receiver_name'],
                'receiver_phone'   => $data['receiver_phone'],
                'province'         => $data['province'],
                'district'         => $data['district'],
                'ward'             => $data['ward'],
                'specific_address' => $data['specific_address'],
                'is_default'       => $isDefault,
            ]);

            return true;
        });
    }

    /**
     * Cập nhật thông tin chi tiết địa chỉ
     */
    public function updateAddress(string $addressId, string $userId, array $data): bool
    {
        return DB::transaction(function () use ($addressId, $userId, $data) {
            $address = UserAddress::query()->where('id', $addressId)->where('user_id', $userId)->first();
            if (!$address) {
                throw new Exception('Không tìm thấy địa chỉ hoặc bạn không có quyền chỉnh sửa.', 404);
            }

            $isDefault = $data['is_default'] ?? $address->is_default;

            // Nếu cập nhật địa chỉ này thành mặc định, hạ toàn bộ các địa chỉ khác của họ xuống false
            if ($isDefault && !$address->is_default) {
                UserAddress::query()->where('user_id', $userId)->update(['is_default' => false]);
            }

            $address->update([
                'receiver_name'    => $data['receiver_name'],
                'receiver_phone'   => $data['receiver_phone'],
                'province'         => $data['province'],
                'district'         => $data['district'],
                'ward'             => $data['ward'],
                'specific_address' => $data['specific_address'],
                'is_default'       => $isDefault,
            ]);

            return true;
        });
    }

    /**
     * Xóa bỏ địa chỉ khỏi sổ địa chỉ
     */
    public function deleteAddress(string $addressId, string $userId): bool
    {
        return DB::transaction(function () use ($addressId, $userId) {
            $address = UserAddress::query()->where('id', $addressId)->where('user_id', $userId)->first();
            if (!$address) {
                throw new Exception('Địa chỉ không tồn tại hoặc bạn không có quyền xóa.', 404);
            }

            
            if ($address->is_default) {
                throw new Exception('Không thể xóa địa chỉ mặc định. Vui lòng đặt địa chỉ khác làm mặc định trước khi xóa.', 400);
            }

            $address->delete("Đã xóa địa chỉ thành công!");
            return true;
        });
    }
}