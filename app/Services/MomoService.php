<?php

namespace App\Services;

use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MomoService
{
    protected string $partnerCode;
    protected string $accessKey;
    protected string $secretKey;
    protected string $endpoint;
    protected string $redirectUrl;
    protected string $ipnUrl;

    public function __construct()
    {
        $this->partnerCode = config('services.momo.partner_code') ?? '';
        $this->accessKey = config('services.momo.access_key') ?? '';
        $this->secretKey = config('services.momo.secret_key') ?? '';
        $this->endpoint = config('services.momo.endpoint') ?? 'https://test-payment.momo.vn/v2/gateway/api/create';
        $this->redirectUrl = config('services.momo.redirect_url') ?? '';
        $this->ipnUrl = config('services.momo.ipn_url') ?? '';
    }

    /**
     * Build request body, sign it, and send it to the MoMo API.
     * Returns the payUrl.
     */
    public function createPaymentUrl(Order $order): string
    {
        $amount = (int) $order->total_amount;
        $timestamp = time();
        // Sử dụng thêm chuỗi ngẫu nhiên uniqid() để tránh trùng lặp requestId/orderId nếu nhấn thanh toán quá nhanh
        $orderId = $order->id . '_' . $timestamp . '_' . uniqid();
        $requestId = $order->id . '_' . $timestamp . '_' . uniqid();
        $orderInfo = "Thanh toan don hang #" . $order->id;
        $extraData = "";
        $requestType = "captureWallet";

        $rawHash = "accessKey=" . $this->accessKey .
            "&amount=" . $amount .
            "&extraData=" . $extraData .
            "&ipnUrl=" . $this->ipnUrl .
            "&orderId=" . $orderId .
            "&orderInfo=" . $orderInfo .
            "&partnerCode=" . $this->partnerCode .
            "&redirectUrl=" . $this->redirectUrl .
            "&requestId=" . $requestId .
            "&requestType=" . $requestType;

        $signature = hash_hmac("sha256", $rawHash, $this->secretKey);

        $payload = [
            'partnerCode' => $this->partnerCode,
            'partnerName' => 'Shopmee',
            'storeId' => $this->partnerCode,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->redirectUrl,
            'ipnUrl' => $this->ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
        ];

        Log::info('MoMo Create Payment URL Payload: ' . json_encode($payload));

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->endpoint, $payload);
        } catch (Exception $e) {
            Log::error('Lỗi call API MoMo: ' . $e->getMessage());
            throw new Exception('Lỗi kết nối đến cổng thanh toán MoMo: ' . $e->getMessage());
        }

        if ($response->failed()) {
            Log::error('MoMo payment creation failed status: ' . $response->status() . ' body: ' . $response->body());
            throw new Exception('Gửi yêu cầu thanh toán MoMo không thành công.');
        }

        $result = $response->json();
        Log::info('MoMo Create Payment URL Response: ' . json_encode($result));

        if (isset($result['resultCode']) && $result['resultCode'] === 0 && isset($result['payUrl'])) {
            return $result['payUrl'];
        }

        $message = $result['message'] ?? 'Lỗi từ phía cổng thanh toán MoMo.';
        throw new Exception($message);
    }

    /**
     * Verify the signature of incoming MoMo callback / IPN data.
     */
    public function verifyCallbackSignature(array $data): bool
    {
        $signature = $data['signature'] ?? '';
        if (empty($signature)) {
            return false;
        }

        $rawHash = "accessKey=" . $this->accessKey .
            "&amount=" . ($data['amount'] ?? '') .
            "&extraData=" . ($data['extraData'] ?? '') .
            "&message=" . ($data['message'] ?? '') .
            "&orderId=" . ($data['orderId'] ?? '') .
            "&orderInfo=" . ($data['orderInfo'] ?? '') .
            "&orderType=" . ($data['orderType'] ?? '') .
            "&partnerCode=" . ($data['partnerCode'] ?? '') .
            "&payType=" . ($data['payType'] ?? '') .
            "&requestId=" . ($data['requestId'] ?? '') .
            "&responseTime=" . ($data['responseTime'] ?? '') .
            "&resultCode=" . ($data['resultCode'] ?? '') .
            "&transId=" . ($data['transId'] ?? '');

        $calculatedSignature = hash_hmac("sha256", $rawHash, $this->secretKey);

        $match = hash_equals($calculatedSignature, $signature);
        if (!$match) {
            Log::warning("MoMo callback signature mismatch. Expected: {$calculatedSignature}, Got: {$signature}");
        }

        return $match;
    }

    /**
     * Process payment result: update order status.
     */
    public function processPaymentResult(array $data): bool
    {
        $orderIdWithTime = $data['orderId'] ?? '';
        $parts = explode('_', $orderIdWithTime);
        $orderId = (int) $parts[0];

        $resultCode = (int) ($data['resultCode'] ?? -1);
        $amount = (int) ($data['amount'] ?? 0);

        try {
            // Sử dụng database transaction kết hợp lockForUpdate để ngăn chặn Race Condition
            // khi cả IPN callback và Client Redirect Verify cùng lúc xử lý một đơn hàng.
            return \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $resultCode, $amount) {
                $order = Order::where('id', $orderId)->lockForUpdate()->first();

                if (!$order) {
                    Log::error("MoMo callback: Order #{$orderId} not found.");
                    return false;
                }

                // 1. Tránh xử lý trùng lặp nếu đơn hàng đã được đánh dấu là Paid (Thành công)
                if ($order->payment_status === PaymentStatus::Paid) {
                    Log::info("MoMo callback: Order #{$orderId} is already marked as Paid.");
                    return true;
                }

                // 2. Tránh xử lý trùng lặp nếu đơn hàng đã được đánh dấu là Failed (Thất bại) trước đó
                if ($order->payment_status === PaymentStatus::Failed) {
                    Log::info("MoMo callback: Order #{$orderId} is already marked as Failed.");
                    return false;
                }

                if ($resultCode === 0) {
                    // Bảo mật: Xác thực số tiền thanh toán thực tế khớp với tổng tiền đơn hàng
                    if ($amount !== (int) $order->total_amount) {
                        Log::error("MoMo callback: Amount mismatch for order #{$orderId}. Expected: {$order->total_amount}, Got: {$amount}");
                        
                        $order->update([
                            'payment_status' => PaymentStatus::Failed->value,
                            'status' => OrderStatus::Failed->value
                        ]);
                        $this->restoreStock($order);
                        return false;
                    }

                    $order->update([
                        'payment_status' => PaymentStatus::Paid->value,
                        'status' => OrderStatus::Pending->value
                    ]);
                    Log::info("MoMo callback: Order #{$orderId} marked as Paid.");
                    return true;
                } else {
                    $order->update([
                        'payment_status' => PaymentStatus::Failed->value,
                        'status' => OrderStatus::Failed->value
                    ]);

                    $this->restoreStock($order);

                    Log::info("MoMo callback: Order #{$orderId} marked as Failed due to resultCode {$resultCode}.");
                    return false;
                }
            });
        } catch (Exception $e) {
            Log::error("Failed to process MoMo payment result for order #{$orderId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Khôi phục số lượng tồn kho sản phẩm khi đơn hàng thất bại
     */
    protected function restoreStock(Order $order): void
    {
        $orderItems = $order->items()->with('productVariant')->get();
        foreach ($orderItems as $item) {
            $variant = $item->productVariant;
            if ($variant) {
                $variant->increment('stock_quantity', $item->quantity);
            }
        }
    }
}
