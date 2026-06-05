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

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Unauthenticated request should be rejected.
     */
    public function test_unauthenticated_user_cannot_access_revenue(): void
    {
        $response = $this->getJson('/api/v1/seller/dashboard/revenue');
        $response->assertStatus(401);
    }

    /**
     * Test: Authenticated user without a shop gets 400 error.
     */
    public function test_user_without_shop_cannot_access_revenue(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/dashboard/revenue');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn chưa đăng ký cửa hàng.',
            ]);
    }

    /**
     * Test: Seller with no sales returns 0 values.
     */
    public function test_seller_with_no_sales_returns_zero_stats(): void
    {
        $user = User::factory()->create();
        Shop::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/dashboard/revenue');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_revenue' => '0.00',
                    'total_orders_completed' => 0,
                    'total_products_sold' => 0,
                    'currency' => 'VND'
                ]
            ]);
    }

    /**
     * Test: Validation error when passing invalid dates.
     */
    public function test_invalid_dates_return_422(): void
    {
        $user = User::factory()->create();
        Shop::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/dashboard/revenue?start_date=invalid-date&end_date=2026-05-30');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    /**
     * Test: Correct calculation of revenue, orders and sold products (only delivered ones count).
     */
    public function test_revenue_and_stats_calculation(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Create another shop and product to verify its revenue is not mixed in
        $otherUser = User::factory()->create();
        $otherShop = Shop::factory()->create(['owner_id' => $otherUser->id]);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id]);
        $otherVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);

        $customer = User::factory()->create();

        // 1. Delivered order belonging to seller shop
        $order1 = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 150000.00, // Revenue contribution: 300,000 VND
        ]);

        // 2. Another delivered order belonging to seller shop
        $order2 = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price' => 200000.00, // Revenue contribution: 600,000 VND
        ]);

        // 3. Delivered order belonging to OTHER shop (should be excluded)
        $order3 = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
        ]);
        OrderItem::create([
            'order_id' => $order3->id,
            'product_variant_id' => $otherVariant->id,
            'quantity' => 5,
            'unit_price' => 100000.00, // 500,000 VND
        ]);

        // 4. Non-delivered order belonging to seller shop (pending - should be excluded)
        $order4 = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::COD,
        ]);
        OrderItem::create([
            'order_id' => $order4->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 500000.00,
        ]);

        // 5. Cancelled order belonging to seller shop (should be excluded)
        $order5 = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Failed,
            'payment_method' => PaymentMethod::COD,
        ]);
        OrderItem::create([
            'order_id' => $order5->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 100000.00,
        ]);

        // Make the request
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/dashboard/revenue');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_revenue' => '900000.00', // 300,000 + 600,000
                    'total_orders_completed' => 2,   // order1, order2
                    'total_products_sold' => 5,      // 2 + 3
                    'currency' => 'VND'
                ]
            ]);
    }

    /**
     * Test: Date filters restrict calculations properly.
     */
    public function test_revenue_with_date_filters(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $customer = User::factory()->create();

        // 1. Order inside range
        $orderIn = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
            'created_at' => '2026-05-20 10:00:00'
        ]);
        OrderItem::create([
            'order_id' => $orderIn->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'unit_price' => 10000.00, // 100,000 VND
        ]);

        // 2. Order outside range (older)
        $orderOld = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
            'created_at' => '2026-05-10 10:00:00'
        ]);
        OrderItem::create([
            'order_id' => $orderOld->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 10000.00,
        ]);

        // 3. Order outside range (newer)
        $orderNew = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::COD,
            'created_at' => '2026-05-30 10:00:00'
        ]);
        OrderItem::create([
            'order_id' => $orderNew->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
            'unit_price' => 10000.00,
        ]);

        // Request inside the range: 2026-05-15 to 2026-05-25
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/dashboard/revenue?start_date=2026-05-15&end_date=2026-05-25');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_revenue' => '100000.00',
                    'total_orders_completed' => 1,
                    'total_products_sold' => 10,
                    'currency' => 'VND'
                ]
            ]);
    }
}
