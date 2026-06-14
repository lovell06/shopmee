<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting public categories list.
     */
    public function test_can_get_all_categories(): void
    {
        // 1. Arrange: Create some categories
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Fashion']);
        Category::factory()->create(['name' => 'Home & Living']);

        // 2. Act: Send a request to GET /api/v1/categories
        $response = $this->getJson('/api/v1/categories');

        // 3. Assert: Verify the status code and json response structure and data
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lấy danh sách danh mục thành công.',
                'data' => [
                    ['name' => 'Electronics'],
                    ['name' => 'Fashion'],
                    ['name' => 'Home & Living'],
                ]
            ]);

        // Check if IDs are returned as well
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    /**
     * Test getting public categories list when empty.
     */
    public function test_can_get_empty_categories_list(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lấy danh sách danh mục thành công.',
                'data' => []
            ]);
    }
}
