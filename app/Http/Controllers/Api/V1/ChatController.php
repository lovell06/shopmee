<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Contracts\ChatbotServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ChatController extends Controller
{
    private ChatbotServiceInterface $chatbotService;

    // IoC Container tự động inject Singleton GeminiService vào đây
    public function __construct(ChatbotServiceInterface $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    #[OA\Post(
        path: "/chat/gemini",
        summary: "Hỏi đáp với AI Gemini",
        description: "Gửi tin nhắn/câu hỏi đến AI Gemini và nhận câu trả lời.",
        operationId: "askGemini",
        tags: ["Chatbot AI"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["message"],
                properties: [
                    new OA\Property(property: "message", type: "string", description: "Nội dung câu hỏi gửi tới AI", example: "Giải thích ngắn gọn cơ chế bất đồng bộ của CPU.")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "reply", type: "string", example: "Cơ chế bất đồng bộ của CPU...")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Dữ liệu đầu vào không hợp lệ"
            ),
            new OA\Response(
                response: 503,
                description: "Dịch vụ AI gặp sự cố"
            )
        ]
    )]
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