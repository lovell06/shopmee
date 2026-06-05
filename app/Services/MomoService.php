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
        $orderId = $order->id . '_' . $timestamp;
        $requestId = $order->id . '_' . $timestamp;
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

        $order = Order::find($orderId);
        if (!$order) {
            Log::error("MoMo callback: Order #{$orderId} not found.");
            return false;
        }

        // Avoid double processing if already paid
        if ($order->payment_status === PaymentStatus::Paid) {
            Log::info("MoMo callback: Order #{$orderId} is already marked as Paid.");
            return true;
        }

        $resultCode = (int) ($data['resultCode'] ?? -1);

        if ($resultCode === 0) {
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

            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                    $orderItems = $order->items()->with('productVariant')->get();
                    foreach ($orderItems as $item) {
                        $variant = $item->productVariant;
                        if ($variant) {
                            $variant->increment('stock_quantity', $item->quantity);
                        }
                    }
                });
            } catch (Exception $e) {
                Log::error("Failed to restore stock for failed MoMo order #{$orderId}: " . $e->getMessage());
            }

            Log::info("MoMo callback: Order #{$orderId} marked as Failed due to resultCode {$resultCode}.");
            return false;
        }
    }
}
