<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Cart;
use App\Models\CartItem;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MomoPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure services.php with dummy values for testing
        config([
            'services.momo.partner_code' => 'MOMOBKUN20180529',
            'services.momo.access_key' => 'klm05TvNBzhg7h7j',
            'services.momo.secret_key' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
            'services.momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'services.momo.redirect_url' => 'http://localhost:3000/payment-success',
            'services.momo.ipn_url' => 'https://localhost:8000/api/v1/payments/momo-ipn',
        ]);
    }

    /**
     * Test successful checkout redirect with Momo
     */
    public function test_checkout_with_momo_returns_pay_url(): void
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id]);
        
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 150000,
            'stock_quantity' => 10,
        ]);

        // Create a cart with items
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        // Fake the MoMo API response
        Http::fake([
            'https://test-payment.momo.vn/v2/gateway/api/create' => Http::response([
                'resultCode' => 0,
                'message' => 'Thành công',
                'payUrl' => 'https://payment.momo.vn/gateway/pay?s=123456',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'user_address_id' => $address->id,
                'payment_method' => PaymentMethod::Momo->value,
                'description' => 'Test momo order',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', PaymentMethod::Momo->value)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'order_id',
                    'total_amount',
                    'payment_method',
                    'payUrl',
                ],
            ]);

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        // Check stock was decremented
        $this->assertEquals(8, $variant->fresh()->stock_quantity);
    }

    /**
     * Test checkout with MoMo when external API fails
     */
    public function test_checkout_with_momo_fails_when_external_api_fails(): void
    {
        $user = User::factory()->create();
        $address = UserAddress::factory()->create(['user_id' => $user->id]);
        
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'stock_quantity' => 5,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        // Fake API error response
        Http::fake([
            'https://test-payment.momo.vn/v2/gateway/api/create' => Http::response([
                'resultCode' => 99,
                'message' => 'Lỗi kết nối hoặc thông tin merchant không hợp lệ',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'user_address_id' => $address->id,
                'payment_method' => PaymentMethod::Momo->value,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Không thể kết nối tới cổng thanh toán MoMo: Lỗi kết nối hoặc thông tin merchant không hợp lệ');
    }

    /**
     * Test MoMo IPN Callback validation - success case
     */
    public function test_momo_ipn_callback_success(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 300000,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $orderIdWithTime = $order->id . '_1234567890';
        
        $ipnData = [
            'partnerCode' => 'MOMOBKUN20180529',
            'orderId' => $orderIdWithTime,
            'requestId' => $orderIdWithTime,
            'amount' => '300000',
            'orderInfo' => 'Thanh toan don hang #' . $order->id,
            'orderType' => 'momo_wallet',
            'transId' => '2301234567',
            'resultCode' => '0',
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
        ];

        // Generate valid signature
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $accessKey = 'klm05TvNBzhg7h7j';
        
        $rawHash = "accessKey=" . $accessKey .
            "&amount=" . $ipnData['amount'] .
            "&extraData=" . $ipnData['extraData'] .
            "&message=" . $ipnData['message'] .
            "&orderId=" . $ipnData['orderId'] .
            "&orderInfo=" . $ipnData['orderInfo'] .
            "&orderType=" . $ipnData['orderType'] .
            "&partnerCode=" . $ipnData['partnerCode'] .
            "&payType=" . $ipnData['payType'] .
            "&requestId=" . $ipnData['requestId'] .
            "&responseTime=" . $ipnData['responseTime'] .
            "&resultCode=" . $ipnData['resultCode'] .
            "&transId=" . $ipnData['transId'];

        $ipnData['signature'] = hash_hmac("sha256", $rawHash, $secretKey);

        $response = $this->postJson('/api/v1/payments/momo-ipn', $ipnData);

        $response->assertStatus(204);
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    /**
     * Test MoMo IPN Callback with invalid signature
     */
    public function test_momo_ipn_callback_invalid_signature(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 300000,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $ipnData = [
            'partnerCode' => 'MOMOBKUN20180529',
            'orderId' => $order->id . '_1234567890',
            'requestId' => $order->id . '_1234567890',
            'amount' => '300000',
            'orderInfo' => 'Thanh toan don hang #' . $order->id,
            'orderType' => 'momo_wallet',
            'transId' => '2301234567',
            'resultCode' => '0',
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
            'signature' => 'invalid_signature_here',
        ];

        $response = $this->postJson('/api/v1/payments/momo-ipn', $ipnData);

        $response->assertStatus(400);
        $this->assertEquals(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    /**
     * Test Client Redirect Verification - success case
     */
    public function test_momo_client_verify_success(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 150000,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $orderIdWithTime = $order->id . '_1234567890';
        
        $verifyData = [
            'partnerCode' => 'MOMOBKUN20180529',
            'orderId' => $orderIdWithTime,
            'requestId' => $orderIdWithTime,
            'amount' => '150000',
            'orderInfo' => 'Thanh toan don hang #' . $order->id,
            'orderType' => 'momo_wallet',
            'transId' => '2301234567',
            'resultCode' => '0',
            'message' => 'Success',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
        ];

        // Generate signature
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $accessKey = 'klm05TvNBzhg7h7j';
        
        $rawHash = "accessKey=" . $accessKey .
            "&amount=" . $verifyData['amount'] .
            "&extraData=" . $verifyData['extraData'] .
            "&message=" . $verifyData['message'] .
            "&orderId=" . $verifyData['orderId'] .
            "&orderInfo=" . $verifyData['orderInfo'] .
            "&orderType=" . $verifyData['orderType'] .
            "&partnerCode=" . $verifyData['partnerCode'] .
            "&payType=" . $verifyData['payType'] .
            "&requestId=" . $verifyData['requestId'] .
            "&responseTime=" . $verifyData['responseTime'] .
            "&resultCode=" . $verifyData['resultCode'] .
            "&transId=" . $verifyData['transId'];

        $verifyData['signature'] = hash_hmac("sha256", $rawHash, $secretKey);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/momo-verify', $verifyData);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Thanh toán MoMo thành công.');

        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    /**
     * Test Client Redirect Verification - fail / cancel case
     */
    public function test_momo_client_verify_failure_restores_stock(): void
    {
        $user = User::factory()->create();
        
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 150000,
            'payment_status' => PaymentStatus::Pending,
        ]);

        // Simulate order item referencing product variant
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price' => 50000,
        ]);

        $orderIdWithTime = $order->id . '_1234567890';
        
        $verifyData = [
            'partnerCode' => 'MOMOBKUN20180529',
            'orderId' => $orderIdWithTime,
            'requestId' => $orderIdWithTime,
            'amount' => '150000',
            'orderInfo' => 'Thanh toan don hang #' . $order->id,
            'orderType' => 'momo_wallet',
            'transId' => '2301234567',
            'resultCode' => '49', // user cancelled
            'message' => 'User cancelled',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
        ];

        // Generate signature
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $accessKey = 'klm05TvNBzhg7h7j';
        
        $rawHash = "accessKey=" . $accessKey .
            "&amount=" . $verifyData['amount'] .
            "&extraData=" . $verifyData['extraData'] .
            "&message=" . $verifyData['message'] .
            "&orderId=" . $verifyData['orderId'] .
            "&orderInfo=" . $verifyData['orderInfo'] .
            "&orderType=" . $verifyData['orderType'] .
            "&partnerCode=" . $verifyData['partnerCode'] .
            "&payType=" . $verifyData['payType'] .
            "&requestId=" . $verifyData['requestId'] .
            "&responseTime=" . $verifyData['responseTime'] .
            "&resultCode=" . $verifyData['resultCode'] .
            "&transId=" . $verifyData['transId'];

        $verifyData['signature'] = hash_hmac("sha256", $rawHash, $secretKey);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/momo-verify', $verifyData);

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Thanh toán MoMo thất bại hoặc bị hủy.');

        $this->assertEquals(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertEquals(OrderStatus::Failed, $order->fresh()->status);

        // Check stock was restored (+3)
        $this->assertEquals(13, $variant->fresh()->stock_quantity);
    }

    /**
     * Test Client Redirect Verification - failed/cancelled duplicate requests are idempotent and do not restore stock twice
     */
    public function test_momo_client_verify_failure_idempotent(): void
    {
        $user = User::factory()->create();
        
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 150000,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price' => 50000,
        ]);

        $orderIdWithTime = $order->id . '_1234567890';
        
        $verifyData = [
            'partnerCode' => 'MOMOBKUN20180529',
            'orderId' => $orderIdWithTime,
            'requestId' => $orderIdWithTime,
            'amount' => '150000',
            'orderInfo' => 'Thanh toan don hang #' . $order->id,
            'orderType' => 'momo_wallet',
            'transId' => '2301234567',
            'resultCode' => '49', // user cancelled
            'message' => 'User cancelled',
            'payType' => 'qr',
            'responseTime' => '1234567890',
            'extraData' => '',
        ];

        // Generate signature
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $accessKey = 'klm05TvNBzhg7h7j';
        
        $rawHash = "accessKey=" . $accessKey .
            "&amount=" . $verifyData['amount'] .
            "&extraData=" . $verifyData['extraData'] .
            "&message=" . $verifyData['message'] .
            "&orderId=" . $verifyData['orderId'] .
            "&orderInfo=" . $verifyData['orderInfo'] .
            "&orderType=" . $verifyData['orderType'] .
            "&partnerCode=" . $verifyData['partnerCode'] .
            "&payType=" . $verifyData['payType'] .
            "&requestId=" . $verifyData['requestId'] .
            "&responseTime=" . $verifyData['responseTime'] .
            "&resultCode=" . $verifyData['resultCode'] .
            "&transId=" . $verifyData['transId'];

        $verifyData['signature'] = hash_hmac("sha256", $rawHash, $secretKey);

        // First call
        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/momo-verify', $verifyData);
        $response1->assertStatus(200);

        $this->assertEquals(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertEquals(13, $variant->fresh()->stock_quantity);

        // Second call (duplicate callback / user refresh)
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/momo-verify', $verifyData);
        
        $response2->assertStatus(200)
            ->assertJsonPath('success', false);

        // Stock must STILL be 13, not 16!
        $this->assertEquals(13, $variant->fresh()->stock_quantity);
    }
}
