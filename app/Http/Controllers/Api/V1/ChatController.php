<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Contracts\ChatbotServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use App\Enums\UserRole;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;

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
        
        $user = $request->user();
        $systemInstruction = null;

        if ($user) {
            $role = $user->role;

            if ($role === UserRole::Buyer) {
                // Fetch buyer context: latest 5 orders and their items
                $orders = Order::where('user_id', $user->id)
                    ->with(['items.productVariant.product'])
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn($o) => [
                        'order_id' => $o->id,
                        'status' => $o->status->value,
                        'payment_status' => $o->payment_status->value ?? 'pending',
                        'total_amount' => $o->total_amount,
                        'created_at' => $o->created_at->toDateTimeString(),
                        'items' => $o->items->map(fn($item) => [
                            'product_name' => $item->productVariant->product->name ?? '',
                            'quantity' => $item->quantity,
                            'price' => $item->unit_price,
                            'variant' => $item->productVariant->variant_name ?? ''
                        ])
                    ]);

                // Fetch platform products context for the buyer to ask budget queries
                $productsList = Product::where('status', \App\Enums\ProductStatus::Active)
                    ->with(['variants'])
                    ->limit(50)
                    ->get()
                    ->map(fn($p) => [
                        'name' => $p->name,
                        'variants' => $p->variants->map(fn($v) => [
                            'name' => $v->variant_name,
                            'price' => (float)$v->price,
                            'stock' => $v->stock_quantity,
                        ])
                    ]);

                $systemInstruction = "Bạn là trợ lý Mee AI của Shopmee. Khách hàng đang hỏi có tên là '{$user->name}' (Email: '{$user->email}'). " .
                    "Lịch sử 5 đơn hàng gần nhất của họ: " . json_encode($orders) . ". " .
                    "Danh sách các sản phẩm đang có trên sàn và các biến thể kèm giá: " . json_encode($productsList) . ". " .
                    "Hãy dựa trên dữ liệu đơn hàng này và danh sách sản phẩm của sàn để trả lời bất cứ câu hỏi nào về trạng thái đơn hàng của họ, hoặc tư vấn sản phẩm cho họ. " .
                    "Khi họ hỏi với số tiền X thì mua được cái gì, hãy tính toán và liệt kê các sản phẩm/biến thể phù hợp với túi tiền của họ từ danh sách sản phẩm trên sàn, có giá bán nhỏ hơn hoặc bằng X. " .
                    "Trả lời một cách chuyên nghiệp, thân thiện và chính xác bằng tiếng Việt.";
            } elseif ($role === UserRole::Seller) {
                // Fetch seller context: shop info, low stock variants, latest shop orders
                $shop = Shop::where('owner_id', $user->id)->first();
                if ($shop) {
                    $productCount = Product::where('shop_id', $shop->id)->count();
                    
                    $lowStockVariants = ProductVariant::whereHas('product', fn($q) => $q->where('shop_id', $shop->id))
                        ->where('stock_quantity', '<=', 5)
                        ->with('product')
                        ->limit(5)
                        ->get()
                        ->map(fn($v) => [
                            'product_name' => $v->product->name ?? '',
                            'variant_name' => $v->variant_name,
                            'stock' => $v->stock_quantity
                        ]);

                    $latestOrders = Order::whereHas('items.productVariant.product', fn($q) => $q->where('shop_id', $shop->id))
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(fn($o) => [
                            'order_id' => $o->id,
                            'status' => $o->status->value,
                            'total_amount' => $o->total_amount,
                            'created_at' => $o->created_at->toDateTimeString()
                        ]);

                    $shopData = [
                        'shop_name' => $shop->name,
                        'total_products' => $productCount,
                        'low_stock_variants' => $lowStockVariants,
                        'latest_orders' => $latestOrders
                    ];

                    $systemInstruction = "Bạn là trợ lý Mee AI hỗ trợ chủ cửa hàng. Người dùng đang hỏi là chủ của cửa hàng '{$shop->name}'. Dữ liệu cửa hàng của họ: " . json_encode($shopData) . ". Hãy giúp họ giải đáp các thắc mắc về hàng tồn kho, đơn hàng mới, hiệu suất bán hàng bằng tiếng Việt.";
                } else {
                    $systemInstruction = "Bạn là trợ lý Mee AI của Shopmee. Người dùng có tài khoản người bán nhưng hiện chưa có cửa hàng nào được đăng ký.";
                }
            } elseif ($role === UserRole::Admin) {
                // Fetch admin context: system overview statistics
                $totalRevenue = Order::where('status', OrderStatus::Delivered)->sum('total_amount');
                $totalShops = Shop::count();
                $totalUsers = User::count();
                
                $latestShops = Shop::latest()->limit(5)->get(['id', 'name', 'created_at'])->map(fn($s) => [
                    'name' => $s->name,
                    'created_at' => $s->created_at->toDateTimeString()
                ]);

                $adminData = [
                    'total_revenue' => $totalRevenue,
                    'total_shops' => $totalShops,
                    'total_users' => $totalUsers,
                    'latest_registered_shops' => $latestShops
                ];

                $systemInstruction = "Bạn là trợ lý Mee AI hỗ trợ Admin quản trị hệ thống Shopmee. Dữ liệu vận hành hệ thống: " . json_encode($adminData) . ". Hãy giúp Admin trả lời các câu hỏi thống kê hoặc báo cáo vận hành một cách súc tích bằng tiếng Việt.";
            }
        }

        // Gọi Service xử lý lõi kèm bối cảnh vai trò
        $aiReply = $this->chatbotService->sendMessage($userMessage, $systemInstruction);

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