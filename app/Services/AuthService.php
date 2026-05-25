<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;

class AuthService
{
    /**
     * Xử lý logic đăng nhập hệ thống
     */
    public function login(array $credentials): array
    {
        // 1. Kiểm tra Email xem có tồn tại trong hệ thống không
        $user = User::query()->where('email', $credentials['email'])->first();

        // 2. Kiểm tra user hoặc mật khẩu có khớp không
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // Quăng lỗi 401 nếu sai thông tin tài khoản
            throw new Exception('Email hoặc mật khẩu không chính xác.', 401);
        }

        // 3. Khởi tạo mã Token bằng Laravel Sanctum
        // Truyền vai trò (role) của user vào tên token hoặc khả năng của token (abilities) nếu cần
        $token = $user->createToken('access_token', [$user->role])->plainTextToken;

        // 4. Trả về thông tin cần thiết cho Controller
        return [
            'user'         => $user,
            'access_token' => $token,
            'token_type'   => 'Bearer'
        ];
    }

    /**
    * Xử lý logic đăng xuất (hủy token)
    */
    public function logout(User $user): void
    {
        // Cách 1: Chỉ xóa đúng cái token mà user đang dùng để đăng xuất hiện tại
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) 
        {
            $token->delete();
        }

        // Cách 2: Nếu muốn đăng xuất khỏi TẤT CẢ các thiết bị (Web, Mobile...) cùng lúc:
        // $user->tokens()->delete();
    }
}