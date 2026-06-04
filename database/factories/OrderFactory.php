<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\UserAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_address_id' => function (array $attributes) {
                $userId = $attributes['user_id'] ?? null;

                // Nếu user_id chưa phải là chuỗi/số (khi chạy standalone factory)
                if (!is_string($userId) && !is_numeric($userId)) {
                    return UserAddress::factory(); 
                }

                return UserAddress::query()
                    ->where('user_id', '=', $userId, 'and') 
                    ->inRandomOrder()
                    ->first()?->id 
                    ?? UserAddress::factory()->create(['user_id' => $userId])->id;
            },
            
            // Tạo một số tiền giả định ngẫu nhiên (sẽ được Seeder tính toán cộng dồn chuẩn xác lại sau)
            'total_amount' => fake()->randomFloat(2, 50000, 2000000),
            'description' => fake()->sentence(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::CashOnDelivery,
        ];
    }
}
