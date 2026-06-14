<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Tạo người dùng test chính
        $this->user = User::factory()->create([
            'name' => 'Nguyen Van A',
            'email' => 'nva@example.com',
            'phone' => '0987654321',
            'password' => Hash::make('password123'),
            'role' => \App\Enums\UserRole::Buyer,
            'status' => \App\Enums\UserStatus::Active,
        ]);

        // Tạo người dùng khác để test trùng email
        $this->otherUser = User::factory()->create([
            'email' => 'other@example.com',
        ]);
    }

    /**
     * Test: Chưa đăng nhập không thể truy cập profile.
     */
    public function test_guest_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertUnauthorized();
    }

    /**
     * Test: Lấy thông tin profile thành công kèm danh sách địa chỉ và tổng tiền.
     */
    public function test_user_can_get_profile_with_addresses_and_spent_amount(): void
    {
        // 1. Tạo địa chỉ cho người dùng
        $address = UserAddress::create([
            'user_id' => $this->user->id,
            'receiver_name' => 'Nguyen Van A',
            'receiver_phone' => '0987654321',
            'province' => 'TP.HCM',
            'district' => 'Quan 1',
            'ward' => 'Ben Nghe',
            'specific_address' => '123 Le Loi',
            'is_default' => true,
        ]);

        // 2. Tạo các đơn hàng (một cái đã thanh toán, một cái chưa)
        Order::create([
            'user_id' => $this->user->id,
            'user_address_id' => $address->id,
            'total_amount' => 500.00,
            'status' => OrderStatus::Delivered->value ?? 'delivered',
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => PaymentMethod::Momo->value ?? 'momo',
        ]);

        Order::create([
            'user_id' => $this->user->id,
            'user_address_id' => $address->id,
            'total_amount' => 300.00,
            'status' => OrderStatus::Pending->value ?? 'pending',
            'payment_status' => PaymentStatus::Pending->value,
            'payment_method' => PaymentMethod::COD->value ?? 'cash_on_delivery',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lấy thông tin hồ sơ thành công.',
                'data' => [
                    'id' => $this->user->id,
                    'name' => 'Nguyen Van A',
                    'email' => 'nva@example.com',
                    'phone' => '0987654321',
                    'role' => 'buyer',
                    'status' => 'active',
                    'total_spent' => 500.00,
                    'addresses' => [
                        [
                            'id' => $address->id,
                            'receiver_name' => 'Nguyen Van A',
                            'receiver_phone' => '0987654321',
                            'province' => 'TP.HCM',
                            'district' => 'Quan 1',
                            'ward' => 'Ben Nghe',
                            'specific_address' => '123 Le Loi',
                            'is_default' => true,
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test: Cập nhật thông tin profile thành công.
     */
    public function test_user_can_update_profile_info(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'Nguyen Van A Moi',
                'email' => 'nva_new@example.com',
                'phone' => '0912345678',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Cập nhật thông tin hồ sơ thành công.',
                'data' => [
                    'name' => 'Nguyen Van A Moi',
                    'email' => 'nva_new@example.com',
                    'phone' => '0912345678',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Nguyen Van A Moi',
            'email' => 'nva_new@example.com',
            'phone' => '0912345678',
        ]);
    }

    /**
     * Test: Cập nhật thông tin thất bại nếu trùng email của người khác.
     */
    public function test_user_cannot_update_profile_with_duplicate_email(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'Nguyen Van A Moi',
                'email' => 'other@example.com',
                'phone' => '0912345678',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test: Cập nhật thông tin thành công nếu giữ nguyên email hiện tại của chính mình.
     */
    public function test_user_can_update_profile_while_keeping_own_email(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'Nguyen Van A Update Name Only',
                'email' => 'nva@example.com',
                'phone' => '0987654321',
            ]);

        $response->assertOk();
    }

    /**
     * Test: Đổi mật khẩu thành công.
     */
    public function test_user_can_change_password(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Thay đổi mật khẩu thành công.',
            ]);

        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    /**
     * Test: Đổi mật khẩu thất bại nếu mật khẩu hiện tại không đúng.
     */
    public function test_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'wrongpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không chính xác.',
            ]);
    }

    /**
     * Test: Đổi mật khẩu thất bại nếu mật khẩu mới không khớp hoặc quá ngắn.
     */
    public function test_user_cannot_change_password_with_invalid_new_password(): void
    {
        // 1. Không khớp
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'differentpassword',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        // 2. Quá ngắn
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'short',
                'new_password_confirmation' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }
}
