<?php

namespace Database\Factories;

use App\Enums\Purpose;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpVerification>
 */
class OtpVerificationFactory extends Factory
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
            'code_hash' => Hash::make('123456'),
            'purpose' => Purpose::UserRegistration,
            'created_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
            'attempt_count' => 0,
        ];
    }
}
