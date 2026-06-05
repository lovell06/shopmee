<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_shop_list(): void
    {
        $user = User::factory()->create(['role' => UserRole::Buyer]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/shops');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Khong co quyen truy cap tai nguyen admin.',
            ]);
    }

    public function test_admin_can_list_users_with_filter_and_search(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $blockedSeller = User::factory()->create([
            'name' => 'Target Seller',
            'email' => 'seller@example.com',
            'phone' => '0912345678',
            'role' => UserRole::Seller,
            'status' => UserStatus::Blocked,
        ]);
        $otherUser = User::factory()->create([
            'role' => UserRole::Buyer,
            'status' => UserStatus::Active,
        ]);

        Shop::factory()->create(['owner_id' => $blockedSeller->id]);
        Order::factory()->create(['user_id' => $blockedSeller->id]);
        Order::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users?role=seller&status=blocked&search=Target');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $blockedSeller->id)
            ->assertJsonPath('data.0.name', 'Target Seller')
            ->assertJsonPath('data.0.email', 'seller@example.com')
            ->assertJsonPath('data.0.phone', '0912345678')
            ->assertJsonPath('data.0.role', UserRole::Seller->value)
            ->assertJsonPath('data.0.status', UserStatus::Blocked->value)
            ->assertJsonPath('data.0.shops_count', 1)
            ->assertJsonPath('data.0.orders_count', 1);
    }

    public function test_admin_can_list_pending_shops(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $pendingOwner = User::factory()->create();
        $activeOwner = User::factory()->create();

        $pendingShop = Shop::factory()->create([
            'owner_id' => $pendingOwner->id,
            'name' => 'Cho duyet Shop',
            'status' => ShopStatus::Pending,
        ]);

        Shop::factory()->create([
            'owner_id' => $activeOwner->id,
            'name' => 'Dang hoat dong Shop',
            'status' => ShopStatus::Active,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/shops?status=pending');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $pendingShop->id)
            ->assertJsonPath('data.0.name', 'Cho duyet Shop')
            ->assertJsonPath('data.0.status', ShopStatus::Pending->value)
            ->assertJsonPath('data.0.owner.id', $pendingOwner->id);
    }

    public function test_admin_can_approve_shop_and_promote_owner_to_seller(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Buyer]);
        $shop = Shop::factory()->create([
            'owner_id' => $owner->id,
            'status' => ShopStatus::Pending,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/shops/{$shop->id}/status", [
                'status' => ShopStatus::Active->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shop_id', $shop->id)
            ->assertJsonPath('data.status', ShopStatus::Active->value)
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.owner.role', UserRole::Seller->value);

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'status' => ShopStatus::Active->value,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'role' => UserRole::Seller->value,
        ]);
    }

    public function test_admin_can_block_user_by_uuid_and_revoke_tokens(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->createToken('mobile');

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$user->id}", [
                'status' => UserStatus::Blocked->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', UserStatus::Blocked->value);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::Blocked->value,
        ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => Hash::make('password123'),
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$user->id}", [
                'status' => UserStatus::Blocked->value,
            ])
            ->assertOk();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tai khoan cua ban da bi khoa. Vui long lien he quan tri vien.',
            ]);
    }

    public function test_admin_must_provide_admin_note_when_hiding_product(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/products/{$product->id}", [
                'status' => ProductStatus::Hidden->value,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['admin_note']);
    }

    public function test_admin_can_hide_product_and_store_admin_note(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/products/{$product->id}", [
                'status' => ProductStatus::Hidden->value,
                'admin_note' => 'San pham vi pham mo ta.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.status', ProductStatus::Hidden->value);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::Hidden->value,
            'admin_note' => 'San pham vi pham mo ta.',
        ]);
    }

    public function test_admin_can_list_products_with_shop_owner_and_category_information(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create([
            'name' => 'Shop Owner',
            'email' => 'owner@example.com',
        ]);
        $shop = Shop::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Tech House',
            'status' => ShopStatus::Active,
        ]);
        $category = Category::factory()->create(['name' => 'Laptop']);

        $targetProduct = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Gaming Laptop Pro',
            'status' => ProductStatus::Hidden,
            'admin_note' => 'Can review lai noi dung san pham.',
        ]);

        ProductVariant::factory()->count(2)->create(['product_id' => $targetProduct->id]);

        Product::factory()->create([
            'name' => 'Other Product',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/products?status=hidden&shop_id={$shop->id}&search=Gaming");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $targetProduct->id)
            ->assertJsonPath('data.0.name', 'Gaming Laptop Pro')
            ->assertJsonPath('data.0.status', ProductStatus::Hidden->value)
            ->assertJsonPath('data.0.admin_note', 'Can review lai noi dung san pham.')
            ->assertJsonPath('data.0.shop.id', $shop->id)
            ->assertJsonPath('data.0.shop.name', 'Tech House')
            ->assertJsonPath('data.0.shop.owner.id', $owner->id)
            ->assertJsonPath('data.0.shop.owner.email', 'owner@example.com')
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.category.name', 'Laptop')
            ->assertJsonPath('data.0.variants_count', 2);
    }

    public function test_admin_can_list_orders_and_filter_by_shop(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create();

        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $productA = Product::factory()->create(['shop_id' => $shopA->id]);
        $productB = Product::factory()->create(['shop_id' => $shopB->id]);

        $variantA = ProductVariant::factory()->create(['product_id' => $productA->id]);
        $variantB = ProductVariant::factory()->create(['product_id' => $productB->id]);

        $matchedOrder = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::Shipping,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
        ]);

        $otherOrder = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Momo,
        ]);

        OrderItem::factory()->create([
            'order_id' => $matchedOrder->id,
            'product_variant_id' => $variantA->id,
            'quantity' => 2,
        ]);

        OrderItem::factory()->create([
            'order_id' => $otherOrder->id,
            'product_variant_id' => $variantB->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/orders?status=shipping&shop_id={$shopA->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matchedOrder->id)
            ->assertJsonPath('data.0.status', OrderStatus::Shipping->value)
            ->assertJsonPath('data.0.shop_ids.0', $shopA->id);
    }
}
