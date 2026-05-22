<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Ensure an admin account exists
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'phone' => '0987654321',
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'password' => Hash::make('adminpassword'),
            ]
        );

        // 2. Create distinct Categories
        $categories = collect([
            'Electronics', 'Fashion', 'Home & Living', 'Health & Beauty', 'Books', 'Sports'
        ])->map(fn($name) => \App\Models\Category::firstOrCreate(['name' => $name]));

        // 3. Create Sellers, Shops, Products, and Variants
        $sellers = User::factory(3)->create([
            'role' => UserRole::Seller,
        ]);

        $shops = $sellers->map(fn($seller) => \App\Models\Shop::factory()->create([
            'owner_id' => $seller->id,
        ]));

        $products = collect();
        $variants = collect();

        foreach ($shops as $shop) {
            $shopProducts = \App\Models\Product::factory(5)->create([
                'shop_id' => $shop->id,
                'category_id' => fn() => $categories->random()->id,
            ]);

            $products = $products->merge($shopProducts);

            foreach ($shopProducts as $product) {
                // Add variants
                $productVariants = \App\Models\ProductVariant::factory(2)->create([
                    'product_id' => $product->id,
                ]);
                $variants = $variants->merge($productVariants);

                // Add product images
                \App\Models\ProductImage::factory(2)->create([
                    'product_id' => $product->id,
                ]);
            }
        }

        // 4. Create Buyers, Addresses, Carts, Cart Items, Orders, and Order Items
        $buyers = User::factory(10)->create([
            'role' => UserRole::Buyer,
        ]);

        foreach ($buyers as $buyer) {
            // Create user address
            \App\Models\UserAddress::factory(fake()->numberBetween(1, 2))->create([
                'user_id' => $buyer->id,
            ]);

            // Create OTP records
            \App\Models\OtpVerification::factory(fake()->numberBetween(0, 2))->create([
                'user_id' => $buyer->id,
            ]);

            // Create a Cart
            $cart = \App\Models\Cart::factory()->create([
                'user_id' => $buyer->id,
            ]);

            // Add some items to the cart
            $cartVariants = $variants->random(fake()->numberBetween(1, 3));
            foreach ($cartVariants as $variant) {
                \App\Models\CartItem::factory()->create([
                    'cart_id' => $cart->id,
                    'product_variant_id' => $variant->id,
                ]);
            }

            // Create a few Orders
            $orderCount = fake()->numberBetween(1, 2);
            for ($i = 0; $i < $orderCount; $i++) {
                $order = \App\Models\Order::factory()->create([
                    'user_id' => $buyer->id,
                ]);

                // Add random order items
                $orderVariants = $variants->random(fake()->numberBetween(1, 3));
                foreach ($orderVariants as $variant) {
                    \App\Models\OrderItem::factory()->create([
                        'order_id' => $order->id,
                        'product_variant_id' => $variant->id,
                        'unit_price' => $variant->price,
                    ]);
                }
            }
        }
    }
}
