<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shop;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Unauthenticated request should be rejected.
     */
    public function test_unauthenticated_user_cannot_update_order_status(): void
    {
        $response = $this->patchJson('/api/v1/seller/orders/1', ['status' => 'confirmed']);
        $response->assertStatus(401);
    }

    /**
     * Test: Authenticated user without a shop gets 400 error.
     */
    public function test_user_without_shop_cannot_update_order_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/seller/orders/1', ['status' => 'confirmed']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn chưa đăng ký cửa hàng.',
            ]);
    }

    /**
     * Test: Updating a non-existent order returns 404.
     */
    public function test_updating_non_existent_order_returns_404(): void
    {
        $user = User::factory()->create();
        Shop::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/seller/orders/99999', ['status' => 'confirmed']);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Đơn hàng không tồn tại.',
            ]);
    }

    /**
     * Test: Updating with invalid status returns validation error (422).
     */
    public function test_updating_with_invalid_status_returns_422(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'description' => 'Test order',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::COD,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/seller/orders/{$order->id}", ['status' => 'invalid-status']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test: Seller cannot update order that has no items from their shop (returns 403).
     */
    public function test_seller_cannot_update_order_from_another_shop(): void
    {
        $user = User::factory()->create();
        Shop::factory()->create(['owner_id' => $user->id]); // Seller's shop

        $otherUser = User::factory()->create();
        $otherShop = Shop::factory()->create(['owner_id' => $otherUser->id]);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id]);
        $otherVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);

        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'description' => 'Test order',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::COD,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $otherVariant->id,
            'description' => 'Item detail',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/seller/orders/{$order->id}", ['status' => 'confirmed']);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật đơn hàng này.',
            ]);
    }

    /**
     * Test: Seller can successfully update order containing items from their shop.
     */
    public function test_seller_can_successfully_update_order_status(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'description' => 'Test order',
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::COD,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'description' => 'Item detail',
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        // 1. Test confirmed
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/seller/orders/{$order->id}", ['status' => 'confirmed']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cập nhật trạng thái đơn hàng thành công',
                'data' => [
                    'order_id' => (string)$order->id,
                    'status' => 'confirmed'
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed'
        ]);

        // 2. Test shipping
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/seller/orders/{$order->id}", ['status' => 'shipping']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => (string)$order->id,
                    'status' => 'shipping'
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'shipping'
        ]);
    }
}
