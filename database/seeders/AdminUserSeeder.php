<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserStatus;
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
                'phone' => '0987654321',
                'password' => Hash::make('12345678'), // Laravel sẽ tự động hash Bcrypt ở đây
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
            ]
        );
    }
}