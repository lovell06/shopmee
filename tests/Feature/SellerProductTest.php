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

    /**
     * Test: Success posting a new product with variants.
     */
    public function test_seller_can_create_product_with_variants(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $category = \App\Models\Category::factory()->create();

        $payload = [
            'category_id' => $category->id,
            'name' => 'Áo thun Polo Classic',
            'description' => 'Mô tả chi tiết Áo thun Polo Classic',
            'variants' => [
                [
                    'sku' => 'POLO-W-M',
                    'variant_name' => 'White - M',
                    'price' => 199000,
                    'stock_quantity' => 50,
                ],
                [
                    'sku' => 'POLO-W-L',
                    'variant_name' => 'White - L',
                    'price' => 209000,
                    'stock_quantity' => 30,
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Đăng sản phẩm và tạo các biến thể thành công',
                'data' => [
                    'name' => 'Áo thun Polo Classic',
                    'variants_count' => 2,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Áo thun Polo Classic',
            'description' => 'Mô tả chi tiết Áo thun Polo Classic',
            'status' => \App\Enums\ProductStatus::Active->value,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'POLO-W-M',
            'variant_name' => 'White - M',
            'price' => '199000.00',
            'stock_quantity' => 50,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'POLO-W-L',
            'variant_name' => 'White - L',
            'price' => '209000.00',
            'stock_quantity' => 30,
        ]);
    }

    /**
     * Test: Creating product fails validation when fields are missing or invalid.
     */
    public function test_create_product_validation_fails_for_invalid_data(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        $payload = [
            'name' => '', // empty name
            'variants' => [], // empty variants
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/products', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'name', 'description', 'variants']);
    }

    /**
     * Test: Success updating a product and its variants.
     */
    public function test_seller_can_update_their_product_and_variants(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $category = \App\Models\Category::factory()->create();
        $newCategory = \App\Models\Category::factory()->create();

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $variant1 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-OLD-1',
            'variant_name' => 'Old Variant 1',
            'price' => 100000.00,
            'stock_quantity' => 10,
        ]);

        $variant2 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-OLD-2',
            'variant_name' => 'Old Variant 2',
            'price' => 200000.00,
            'stock_quantity' => 20,
        ]);

        $payload = [
            'category_id' => $newCategory->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'variants' => [
                [
                    'id' => $variant1->id,
                    'sku' => 'SKU-NEW-1',
                    'variant_name' => 'New Variant 1',
                    'price' => 120000,
                    'stock_quantity' => 15,
                ],
                [
                    'id' => $variant2->id,
                    'sku' => 'SKU-NEW-2',
                    'variant_name' => 'New Variant 2',
                    'price' => 220000,
                    'stock_quantity' => 25,
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/seller/products/{$product->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cập nhật thông tin sản phẩm và biến thể thành công',
                'data' => [
                    'product_id' => $product->id,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $newCategory->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant1->id,
            'sku' => 'SKU-NEW-1',
            'variant_name' => 'New Variant 1',
            'price' => '120000.00',
            'stock_quantity' => 15,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant2->id,
            'sku' => 'SKU-NEW-2',
            'variant_name' => 'New Variant 2',
            'price' => '220000.00',
            'stock_quantity' => 25,
        ]);
    }

    /**
     * Test: Seller cannot update another shop's product.
     */
    public function test_seller_cannot_update_product_of_another_shop(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        $otherShop = Shop::factory()->create();
        $category = \App\Models\Category::factory()->create();

        $product = Product::factory()->create([
            'shop_id' => $otherShop->id,
            'category_id' => $category->id,
            'name' => 'Other Product',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $payload = [
            'category_id' => $category->id,
            'name' => 'Attempted Update Name',
            'description' => 'Attempted Description',
            'variants' => [
                [
                    'id' => $variant->id,
                    'sku' => 'SKU-ATTEMPT',
                    'variant_name' => 'Attempted Name',
                    'price' => 100000,
                    'stock_quantity' => 10,
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/seller/products/{$product->id}", $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn không có quyền chỉnh sửa sản phẩm này',
            ]);
    }

    /**
     * Test: Unauthenticated user cannot delete a product.
     */
    public function test_unauthenticated_user_cannot_delete_product(): void
    {
        $response = $this->deleteJson('/api/v1/seller/products/1');
        $response->assertStatus(401);
    }

    /**
     * Test: Authenticated user without a shop gets 400 when deleting.
     */
    public function test_user_without_shop_cannot_delete_product(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/seller/products/1');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn chưa đăng ký cửa hàng.',
            ]);
    }

    /**
     * Test: Seller can successfully soft delete their own product.
     */
    public function test_seller_can_soft_delete_their_own_product(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/seller/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công',
            ]);

        // Assert it is soft-deleted (soft deleted records remain in DB but are queried via withTrashed())
        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    /**
     * Test: Seller cannot delete another shop's product.
     */
    public function test_seller_cannot_delete_product_of_another_shop(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);

        $otherShop = Shop::factory()->create();
        $product = Product::factory()->create(['shop_id' => $otherShop->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/seller/products/{$product->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn không có quyền xóa sản phẩm này',
            ]);

        // Assert it is not deleted
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test: Deleting non-existent product returns 404.
     */
    public function test_deleting_non_existent_product_returns_404(): void
    {
        $user = User::factory()->create();
        Shop::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/seller/products/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ]);
    }
}

