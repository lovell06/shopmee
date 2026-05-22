<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
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
            'receiver_name' => fake()->name(),
            'receiver_phone' => fake()->numerify('0#########'),
            'province' => fake()->state(),
            'district' => fake()->city(),
            'ward' => fake()->streetName(),
            'specific_address' => fake()->streetAddress(),
            'is_default' => fake()->boolean(10),
        ];
    }
}
