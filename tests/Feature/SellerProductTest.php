<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shop;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Unauthenticated request should be redirected/rejected (401 or login redirect).
     */
    public function test_unauthenticated_user_cannot_access_seller_products(): void
    {
        $response = $this->getJson('/api/v1/seller/products');
        $response->assertStatus(401);
    }

    /**
     * Test: Authenticated user without a shop gets a 400 error.
     */
    public function test_authenticated_user_without_shop_gets_400(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn chưa đăng ký cửa hàng.',
            ]);
    }

    /**
     * Test: Success listing of seller products.
     */
    public function test_authenticated_seller_can_list_their_products(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        // Create 2 products for this shop with distinct created_at times to guarantee order
        $product1 = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Polo Shirt Classic',
            'status' => ProductStatus::Active,
            'created_at' => now()->subMinute(),
        ]);
        $product2 = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Jean Pants Blue',
            'status' => ProductStatus::Hidden,
            'created_at' => now(),
        ]);

        // Create variants
        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'sku' => 'POLO-DEN-L',
            'variant_name' => 'Black - L',
            'price' => 250000.00,
            'stock_quantity' => 45,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'sku' => 'JEAN-BLU-M',
            'variant_name' => 'Blue - M',
            'price' => 350000.00,
            'stock_quantity' => 20,
        ]);

        // Create images
        ProductImage::factory()->create([
            'product_id' => $product1->id,
            'image' => 'polo-den.jpg',
        ]);

        // Create a product for another shop to ensure it isn't listed
        $otherShop = Shop::factory()->create();
        Product::factory()->create([
            'shop_id' => $otherShop->id,
            'name' => 'Other Shop Product',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'created_at',
                        'variants' => [
                            '*' => [
                                'id',
                                'sku',
                                'variant_name',
                                'price',
                                'stock_quantity',
                            ]
                        ],
                        'images' => [
                            '*' => [
                                'id',
                                'image_url',
                            ]
                        ]
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ]
            ]);

        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertCount(2, $responseData['data']);
        
        // Assert only this seller's products are returned, ordered by created_at desc
        // So the most recently created product ($product2) should be first
        $this->assertEquals('Jean Pants Blue', $responseData['data'][0]['name']);
        $this->assertEquals('Polo Shirt Classic', $responseData['data'][1]['name']);

        // Check variant price is formatted string
        $this->assertEquals('250000.00', $responseData['data'][1]['variants'][0]['price']);
        $this->assertEquals(45, $responseData['data'][1]['variants'][0]['stock_quantity']);

        // Check image url
        $this->assertStringContainsString('polo-den.jpg', $responseData['data'][1]['images'][0]['image_url']);
    }

    /**
     * Test: Status filtering (active, pending, hidden).
     */
    public function test_seller_can_filter_products_by_status(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Active Product',
            'status' => ProductStatus::Active,
        ]);
        Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Pending Product',
            'status' => ProductStatus::Pending,
        ]);

        // Filter active
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products?status=active');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active Product', $data[0]['name']);

        // Filter pending
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products?status=pending');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Pending Product', $data[0]['name']);

        // Invalid status returns validation error
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products?status=invalid_status');

        $response->assertStatus(422);
    }

    /**
     * Test: Search product by name or variant SKU.
     */
    public function test_seller_can_search_products_by_name_or_sku(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        $product1 = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Special Coffee Cup',
        ]);
        $product2 = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Stainless Water Bottle',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'sku' => 'CUP-SP-001',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'sku' => 'BTL-ST-999',
        ]);

        // Search by name
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products?search=Coffee');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Special Coffee Cup', $data[0]['name']);

        // Search by SKU
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/seller/products?search=BTL-ST');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Stainless Water Bottle', $data[0]['name']);
    }
}
