<?php

namespace Tests\Feature;

use App\Contracts\ChatbotServiceInterface;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ChatbotContextTest extends TestCase
{
    use RefreshDatabase;

    private $chatbotServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock ChatbotServiceInterface to intercept context parameter
        $this->chatbotServiceMock = Mockery::mock(ChatbotServiceInterface::class);
        $this->app->instance(ChatbotServiceInterface::class, $this->chatbotServiceMock);
    }

    public function test_unauthenticated_user_cannot_access_chatbot()
    {
        $response = $this->postJson('/api/v1/chat/gemini', [
            'message' => 'Hello'
        ]);

        $response->assertStatus(401);
    }

    public function test_buyer_receives_buyer_system_instruction()
    {
        $buyer = User::factory()->create([
            'role' => UserRole::Buyer
        ]);

        $seller = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'variant_name' => 'Red, XL',
            'price' => 150000,
            'stock_quantity' => 10
        ]);

        // Create an order for the buyer
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'total_amount' => 150000,
            'status' => OrderStatus::Pending,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 150000
        ]);

        // Assert that sendMessage is called with systemInstruction containing the order data
        $this->chatbotServiceMock->shouldReceive('sendMessage')
            ->once()
            ->with('Hello Mee AI', Mockery::on(function ($systemInstruction) use ($order, $product) {
                return str_contains($systemInstruction, 'Lịch sử 5 đơn hàng gần nhất')
                    && str_contains($systemInstruction, $order->id)
                    && str_contains($systemInstruction, $product->name);
            }))
            ->andReturn('Hello, I see your order.');

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/v1/chat/gemini', [
                'message' => 'Hello Mee AI'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reply', 'Hello, I see your order.');
    }

    public function test_seller_receives_seller_system_instruction()
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller
        ]);

        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'variant_name' => 'Blue, L',
            'stock_quantity' => 3 // Low stock (<= 5)
        ]);

        // Mock Gemini response
        $this->chatbotServiceMock->shouldReceive('sendMessage')
            ->once()
            ->with('Hello Shop AI', Mockery::on(function ($systemInstruction) use ($shop, $product) {
                return str_contains($systemInstruction, 'chủ của cửa hàng')
                    && str_contains($systemInstruction, $shop->name)
                    && str_contains($systemInstruction, $product->name);
            }))
            ->andReturn('Hello Shop Owner.');

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson('/api/v1/chat/gemini', [
                'message' => 'Hello Shop AI'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reply', 'Hello Shop Owner.');
    }

    public function test_admin_receives_admin_system_instruction()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin
        ]);

        // Mock Gemini response
        $this->chatbotServiceMock->shouldReceive('sendMessage')
            ->once()
            ->with('Hello Admin AI', Mockery::on(function ($systemInstruction) {
                return str_contains($systemInstruction, 'quản trị hệ thống')
                    && str_contains($systemInstruction, 'Dữ liệu vận hành hệ thống');
            }))
            ->andReturn('Hello Admin.');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/chat/gemini', [
                'message' => 'Hello Admin AI'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reply', 'Hello Admin.');
    }
}
