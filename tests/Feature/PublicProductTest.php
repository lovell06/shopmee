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
}
