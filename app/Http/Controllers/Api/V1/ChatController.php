<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Contracts\ChatbotServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    private ChatbotServiceInterface $chatbotService;

    // IoC Container tự động inject Singleton GeminiService vào đây
    public function __construct(ChatbotServiceInterface $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    public function ask(Request $request): JsonResponse
    {
        // Kiểm soát chặt chẽ dữ liệu đầu vào để bảo vệ tài nguyên hệ thống
        $request->validate([
            'message' => 'required|string|max:5000', 
        ]);

        $userMessage = $request->input('message');
        
        // Gọi Service xử lý lõi
        $aiReply = $this->chatbotService->sendMessage($userMessage);

        if (is_null($aiReply)) {
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống xử lý AI gặp sự cố kỹ thuật. Vui lòng thử lại sau.'
            ], 503); // Service Unavailable
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reply' => $aiReply
            ]
        ], 200);
    }
}