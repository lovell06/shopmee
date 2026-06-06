<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shop;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Success getting public product detail by ID.
     */
    public function test_can_get_product_detail_by_id(): void
    {
        $shop = Shop::factory()->create(['name' => 'Shop A']);
        $category = Category::factory()->create(['name' => 'Clothing']);

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Cool Polo Shirt',
            'description' => 'A very cool polo shirt.',
            'status' => \App\Enums\ProductStatus::Active,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'POLO-W-M',
            'variant_name' => 'White - M',
            'price' => 199000,
            'stock_quantity' => 50,
        ]);

        $image = ProductImage::factory()->create([
            'product_id' => $product->id,
            'image' => 'images/polo.jpg',
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lấy chi tiết sản phẩm thành công.',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Cool Polo Shirt',
                    'description' => 'A very cool polo shirt.',
                    'status' => 'active',
                    'category' => [
                        'id' => $category->id,
                        'name' => 'Clothing',
                    ],
                    'shop' => [
                        'id' => $shop->id,
                        'name' => 'Shop A',
                    ],
                    'variants' => [
                        [
                            'id' => $variant->id,
                            'sku' => 'POLO-W-M',
                            'variant_name' => 'White - M',
                            'price' => '199000.00',
                            'stock_quantity' => 50,
                        ]
                    ],
                    'images' => [
                        [
                            'id' => $image->id,
                            'image_url' => asset('storage/images/polo.jpg'),
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test: Product details return 404 when product does not exist.
     */
    public function test_get_product_detail_returns_404_if_not_found(): void
    {
        $response = $this->getJson('/api/v1/products/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ]);
    }

    /**
     * Test: Success searching products by keyword.
     */
    public function test_can_search_products_by_keyword(): void
    {
        $shop = Shop::factory()->create();
        $category = Category::factory()->create();

        $product1 = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Special Coffee Cup',
            'description' => 'A nice cup for your coffee.',
            'status' => \App\Enums\ProductStatus::Active,
        ]);

        $product2 = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Stainless Water Bottle',
            'status' => \App\Enums\ProductStatus::Active,
        ]);

        // Create a hidden product to ensure it's not searched
        $product3 = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Secret Coffee Beans',
            'status' => \App\Enums\ProductStatus::Hidden,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'sku' => 'CUP-SP-001',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'sku' => 'BTL-ST-999',
        ]);

        // 1. Search by name
        $response = $this->getJson('/api/v1/products/search?q=Coffee');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Special Coffee Cup', $data[0]['name']);

        // 2. Search by SKU
        $response = $this->getJson('/api/v1/products/search?q=BTL-ST'); 
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Stainless Water Bottle', $data[0]['name']);
    }
}
