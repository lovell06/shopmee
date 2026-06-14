<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller;
    protected Shop $shop;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a buyer
        $this->buyer = User::factory()->create();

        // Create a seller and their shop
        $this->seller = User::factory()->create();
        $this->shop = Shop::factory()->create(['owner_id' => $this->seller->id]);

        // Create a product in that shop
        $this->product = Product::factory()->create(['shop_id' => $this->shop->id]);
        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'variant_name' => 'Red, XL',
        ]);
    }

    public function test_guest_cannot_review_product(): void
    {
        $response = $this->postJson("/api/v1/orders/1/products/{$this->product->id}/review", [
            'rating' => 5,
            'comment' => 'Great product!'
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_review_non_existent_order(): void
    {
        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/9999/products/{$this->product->id}/review", [
                'rating' => 5,
                'comment' => 'Great product!'
            ]);

        $response->assertStatus(404);
    }

    public function test_user_cannot_review_order_of_another_user(): void
    {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'status' => OrderStatus::Delivered->value
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/products/{$this->product->id}/review", [
                'rating' => 5,
                'comment' => 'Great product!'
            ]);

        $response->assertStatus(404); // returns 404 since we filter by user_id in controller query
    }

    public function test_user_cannot_review_undelivered_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'status' => OrderStatus::Pending->value
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/products/{$this->product->id}/review", [
                'rating' => 5,
                'comment' => 'Great product!'
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Bạn chỉ có thể đánh giá sản phẩm sau khi đơn hàng được giao thành công.');
    }

    public function test_user_cannot_review_product_not_in_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'status' => OrderStatus::Delivered->value
        ]);
        // Do not add the item to the order

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/products/{$this->product->id}/review", [
                'rating' => 5,
                'comment' => 'Great product!'
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Sản phẩm này không nằm trong đơn hàng của bạn.');
    }

    public function test_user_can_successfully_review_product_without_image(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'status' => OrderStatus::Delivered->value
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/products/{$this->product->id}/review", [
                'rating' => 4,
                'comment' => 'Very good build quality.'
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.rating', 4);
        $response->assertJsonPath('data.comment', 'Very good build quality.');

        $this->assertDatabaseHas('product_reviews', [
            'user_id' => $this->buyer->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'variant_name' => 'Red, XL',
            'rating' => 4,
            'comment' => 'Very good build quality.'
        ]);
    }

    public function test_user_can_successfully_review_product_with_image(): void
    {
        Storage::fake('public');

        $order = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'status' => OrderStatus::Delivered->value
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
        ]);

        $file = UploadedFile::fake()->create('review_image.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/products/{$this->product->id}/review", [
                'rating' => 5,
                'comment' => 'Stunning!',
                'image' => $file
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $review = ProductReview::first();
        $this->assertNotNull($review->image);
        Storage::disk('public')->assertExists($review->image);
    }

    public function test_user_cannot_double_review_product(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->buyer->id,
            'status' => OrderStatus::Delivered->value
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
        ]);

        // First review
        ProductReview::create([
            'user_id' => $this->buyer->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'First review'
        ]);

        // Second review attempt
        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/products/{$this->product->id}/review", [
                'rating' => 4,
                'comment' => 'Second review'
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Bạn đã đánh giá sản phẩm này trong đơn hàng này rồi.');
    }
}
