<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected Cart $cart;
    protected ProductVariant $variant1;
    protected ProductVariant $variant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create(['role' => \App\Enums\UserRole::Buyer]);
        $this->cart = Cart::create(['user_id' => $this->buyer->id]);

        $product = Product::factory()->create();
        $this->variant1 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-001',
            'variant_name' => 'Red - M',
            'price' => 100000.00,
            'stock_quantity' => 10,
        ]);
        $this->variant2 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-002',
            'variant_name' => 'Blue - L',
            'price' => 120000.00,
            'stock_quantity' => 5,
        ]);
    }

    /**
     * Test: Get cart count (badge count).
     */
    public function test_buyer_can_get_cart_badge_count(): void
    {
        CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 3,
        ]);

        CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant2->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->getJson('/api/v1/cart/count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 5,
            ]);
    }

    /**
     * Test: Update cart item quantity successfully.
     */
    public function test_buyer_can_update_cart_item_quantity(): void
    {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->putJson('/api/v1/cart/update', [
                'cart_item_id' => $item->id,
                'quantity' => 5,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Đã cập nhật số lượng sản phẩm thành công!',
            ]);

        $this->assertEquals(5, $item->fresh()->quantity);
    }

    /**
     * Test: Updating cart item quantity fails if it exceeds stock.
     */
    public function test_buyer_cannot_update_cart_item_quantity_exceeding_stock(): void
    {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->putJson('/api/v1/cart/update', [
                'cart_item_id' => $item->id,
                'quantity' => 15, // Stock is 10
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Sản phẩm này chỉ còn 10 cái trong kho, không đủ đáp ứng.',
            ]);

        $this->assertEquals(1, $item->fresh()->quantity);
    }

    /**
     * Test: Remove cart item successfully.
     */
    public function test_buyer_can_remove_cart_item(): void
    {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->deleteJson("/api/v1/cart/{$item->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!',
            ]);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    /**
     * Test: Bulk remove cart items successfully.
     */
    public function test_buyer_can_bulk_remove_cart_items(): void
    {
        $item1 = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 1,
        ]);
        $item2 = CartItem::create([
            'cart_id' => $this->cart->id,
            'product_variant_id' => $this->variant2->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/cart/bulk-delete', [
                'cart_item_ids' => [$item1->id, $item2->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Đã xóa các sản phẩm được chọn khỏi giỏ hàng!',
            ]);

        $this->assertDatabaseMissing('cart_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $item2->id]);
    }

    /**
     * Test: User cannot modify another user's cart item.
     */
    public function test_buyer_cannot_modify_another_buyers_cart_item(): void
    {
        $otherBuyer = User::factory()->create(['role' => \App\Enums\UserRole::Buyer]);
        $otherCart = Cart::create(['user_id' => $otherBuyer->id]);
        $otherItem = CartItem::create([
            'cart_id' => $otherCart->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 1,
        ]);

        // Attempt update
        $responseUpdate = $this->actingAs($this->buyer, 'sanctum')
            ->putJson('/api/v1/cart/update', [
                'cart_item_id' => $otherItem->id,
                'quantity' => 2,
            ]);
        $responseUpdate->assertStatus(404);

        // Attempt delete
        $responseDelete = $this->actingAs($this->buyer, 'sanctum')
            ->deleteJson("/api/v1/cart/{$otherItem->id}");
        $responseDelete->assertStatus(404);

        // Attempt bulk delete
        $responseBulkDelete = $this->actingAs($this->buyer, 'sanctum')
            ->postJson('/api/v1/cart/bulk-delete', [
                'cart_item_ids' => [$otherItem->id],
            ]);
        $responseBulkDelete->assertStatus(404);
    }
}
