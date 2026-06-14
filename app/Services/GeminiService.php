<?php

namespace App\Services;

use App\Contracts\ChatbotServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements ChatbotServiceInterface
{
    private ?string $apiKey;
    private ?string $baseUrl;
    private ?string $model;

    public function __construct()
    {
        $this->apiKey  = config('services.gemini.api_key');
        $this->baseUrl = config('services.gemini.base_url');
        $this->model   = config('services.gemini.model');
    }

    public function sendMessage(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini Service: API key is not configured in .env (GEMINI_API_KEY).');
            return null;
        }

        if (empty($this->baseUrl) || empty($this->model)) {
            Log::error('Gemini Service: Base URL or Model is missing.');
            return null;
        }

        // Xây dựng endpoint chuẩn hóa của Google API
        $endpoint = "{$this->baseUrl}{$this->model}:generateContent?key={$this->apiKey}";

        // Tối ưu cấu trúc JSON payload ở mức tối giản, tránh dữ liệu rác thừa thãi
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        try {
            // Thực hiện POST Request với thiết lập Timeout 
            // Tránh việc API treo làm nghẽn luồng xử lý (worker) của Server
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->timeout(30) 
            ->post($endpoint, $payload);

            if ($response->successful()) {
                // Trích xuất dữ liệu trực tiếp bằng dot notation tăng tốc độ phân tích mảng
                return $response->json('candidates.0.content.parts.0.text');
            }

            // Ghi nhận log chi tiết lỗi từ API phục vụ việc gỡ lỗi
            Log::error('Gemini API Fail Response:', [
                'status' => $response->status(),
                'body'   => $response->body()
            ]);
            
            // Nếu lỗi do API Key không hợp lệ, trả về thông báo trực quan trên UI
            $errorMsg = $response->json('error.message');
            if ($response->status() === 400 && str_contains($errorMsg, 'API key not valid')) {
                return '⚠️ Khóa API Gemini hiện tại trong tệp .env không hợp lệ (API key not valid). Vui lòng kiểm tra và cấu hình lại khóa chính xác.';
            }

            return null;

        } catch (\Exception $e) {
            // Bắt lỗi hệ thống (Network Down, DNS Resolution Fail, Timeout...)
            Log::critical('Gemini Service Core Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }
}