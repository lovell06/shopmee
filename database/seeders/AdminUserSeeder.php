<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // Kiểm tra xem admin đã tồn tại chưa để tránh lỗi duplicate email
        User::firstOrCreate(
            ['email' => 'admin@admin.com'], // Điều kiện tìm kiếm
            [
                'name' => 'Administrator',
                'password' => Hash::make('123456'), // Laravel sẽ tự động hash Bcrypt ở đây
                'role' => 'admin' // Thay đổi tùy theo cấu hình phân quyền của bạn
            ]
        );
    }
}