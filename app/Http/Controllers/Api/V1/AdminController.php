<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Api\V1\Admin\AdminOrderListRequest;
use App\Http\Requests\Api\V1\Admin\AdminProductListRequest;
use App\Http\Requests\Api\V1\Admin\AdminProductStatusRequest;
use App\Http\Requests\Api\V1\Admin\AdminShopListRequest;
use App\Http\Requests\Api\V1\Admin\AdminShopStatusRequest;
use App\Http\Requests\Api\V1\Admin\AdminUserListRequest;
use App\Http\Requests\Api\V1\Admin\AdminUserStatusRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminController extends Controller
{
    private function ensureAdminAccess(): ?JsonResponse
    {
        $user = Auth::user();

        if ($user !== null && $user->role === UserRole::Admin) {
            return null;
        }

        return $this->unauthorizedResponse();
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Khong co quyen truy cap tai nguyen admin.',
        ], 403);
    }

    #[OA\Get(
        path: "/admin/shops",
        summary: "Xem danh sách Shop",
        description: "Lấy danh sách các cửa hàng trong hệ thống (dành cho Admin).",
        operationId: "listShops",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", description: "Trạng thái Shop", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "start_date", in: "query", description: "Từ ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Đến ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", description: "Số lượng bản ghi trên trang", required: false, schema: new OA\Schema(type: "integer", default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin")
        ]
    )]
    public function listShops(AdminShopListRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $shops = Shop::query()
            ->with(['owner:id,name,email,role'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->input('start_date') ?? $request->input('from_date'),
                fn ($query, $date) => $query->whereDate('created_at', '>=', $date)
            )
            ->when(
                $request->input('end_date') ?? $request->input('to_date'),
                fn ($query, $date) => $query->whereDate('created_at', '<=', $date)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('limit', 15))
            ->through(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'status' => $shop->status->value,
                'owner' => $shop->owner ? [
                    'id' => $shop->owner->id,
                    'name' => $shop->owner->name,
                    'email' => $shop->owner->email,
                    'role' => $shop->owner->role->value,
                ] : null,
                'created_at' => $shop->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $shops->items(),
            'meta' => [
                'current_page' => $shops->currentPage(),
                'per_page' => $shops->perPage(),
                'total' => $shops->total(),
            ],
        ], 200);
    }

    #[OA\Get(
        path: "/admin/users",
        summary: "Xem danh sách User",
        description: "Lấy danh sách người dùng trong hệ thống (dành cho Admin).",
        operationId: "listUsers",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", description: "Trạng thái người dùng", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "role", in: "query", description: "Vai trò người dùng", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", description: "Từ khóa tìm kiếm (Tên, email, số điện thoại)", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "start_date", in: "query", description: "Từ ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Đến ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", description: "Số lượng bản ghi trên trang", required: false, schema: new OA\Schema(type: "integer", default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin")
        ]
    )]
    public function listUsers(AdminUserListRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $users = User::query()
            ->withCount(['shops', 'orders'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->filled('role'),
                fn ($query) => $query->where('role', $request->input('role'))
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($subQuery) use ($request) {
                    $search = $request->input('search');

                    $subQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
            )
            ->when(
                $request->input('start_date') ?? $request->input('from_date'),
                fn ($query, $date) => $query->whereDate('created_at', '>=', $date)
            )
            ->when(
                $request->input('end_date') ?? $request->input('to_date'),
                fn ($query, $date) => $query->whereDate('created_at', '<=', $date)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('limit', 15))
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'status' => $user->status->value,
                'shops_count' => $user->shops_count,
                'orders_count' => $user->orders_count,
                'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 200);
    }

    #[OA\Get(
        path: "/admin/products",
        summary: "Xem danh sách tất cả sản phẩm",
        description: "Lấy danh sách tất cả sản phẩm toàn sàn (dành cho Admin).",
        operationId: "listAdminProducts",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", description: "Trạng thái sản phẩm", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "shop_id", in: "query", description: "Lọc theo ID Shop", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "search", in: "query", description: "Từ khóa tìm kiếm sản phẩm", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "start_date", in: "query", description: "Từ ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Đến ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", description: "Số lượng bản ghi trên trang", required: false, schema: new OA\Schema(type: "integer", default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin")
        ]
    )]
    public function listProducts(AdminProductListRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $products = Product::query()
            ->with([
                'shop:id,owner_id,name,status',
                'shop.owner:id,name,email',
                'category:id,name',
            ])
            ->withCount(['variants', 'images'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->filled('shop_id'),
                fn ($query) => $query->where('shop_id', $request->integer('shop_id'))
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($subQuery) use ($request) {
                    $search = $request->input('search');

                    $subQuery
                        ->where('products.name', 'like', '%' . $search . '%')
                        ->orWhereHas('shop', fn ($shopQuery) => $shopQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', '%' . $search . '%'));
                })
            )
            ->when(
                $request->input('start_date') ?? $request->input('from_date'),
                fn ($query, $date) => $query->whereDate('created_at', '>=', $date)
            )
            ->when(
                $request->input('end_date') ?? $request->input('to_date'),
                fn ($query, $date) => $query->whereDate('created_at', '<=', $date)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('limit', 15))
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'status' => $product->status->value,
                'admin_note' => $product->admin_note,
                'shop' => $product->shop ? [
                    'id' => $product->shop->id,
                    'name' => $product->shop->name,
                    'status' => $product->shop->status->value,
                    'owner' => $product->shop->owner ? [
                        'id' => $product->shop->owner->id,
                        'name' => $product->shop->owner->name,
                        'email' => $product->shop->owner->email,
                    ] : null,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'variants_count' => $product->variants_count,
                'images_count' => $product->images_count,
                'created_at' => $product->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ], 200);
    }

    #[OA\Patch(
        path: "/admin/shops/{shop}/status",
        summary: "Cập nhật trạng thái Shop",
        description: "Phê duyệt hoạt động hoặc khóa Shop (dành cho Admin).",
        operationId: "updateShopStatus",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "shop", in: "path", description: "ID của Shop", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["pending", "active", "rejected", "blocked"], example: "active")
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
                        new OA\Property(property: "message", type: "string", example: "Duyet shop thanh cong. Quyen han cua chu shop da duoc cap nhat.")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin"),
            new OA\Response(response: 404, description: "Không tìm thấy Shop")
        ]
    )]
    public function updateShopStatus(Shop $shop, AdminShopStatusRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $shop->loadMissing('owner');

        $status = $request->input('status');
        $shop->status = $status;
        $shop->save();

        if ($status === ShopStatus::Active->value && $shop->owner) {
            $shop->owner->update(['role' => UserRole::Seller]);
        }

        return response()->json([
            'success' => true,
            'message' => $status === ShopStatus::Active->value
                ? 'Duyet shop thanh cong. Quyen han cua chu shop da duoc cap nhat.'
                : 'Cap nhat trang thai shop thanh cong.',
            'data' => [
                'shop_id' => $shop->id,
                'status' => $shop->status->value,
                'owner' => $shop->owner ? [
                    'id' => $shop->owner->id,
                    'role' => $shop->owner->role->value,
                ] : null,
            ],
        ], 200);
    }

    #[OA\Patch(
        path: "/admin/users/{user}",
        summary: "Cập nhật trạng thái người dùng",
        description: "Khóa hoặc mở khóa hoạt động của người dùng (dành cho Admin).",
        operationId: "updateUserStatus",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "user", in: "path", description: "ID của người dùng", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["active", "blocked"], example: "active")
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
                        new OA\Property(property: "message", type: "string", example: "Cap nhat trang thai nguoi dung thanh cong.")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin"),
            new OA\Response(response: 404, description: "Không tìm thấy người dùng")
        ]
    )]
    public function updateUserStatus(User $user, AdminUserStatusRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $status = $request->input('status');
        $user->status = $status;
        $user->save();

        if ($status === UserStatus::Blocked->value) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cap nhat trang thai nguoi dung thanh cong.',
            'data' => [
                'user_id' => $user->id,
                'status' => $user->status->value,
            ],
        ], 200);
    }

    #[OA\Patch(
        path: "/admin/products/{product}",
        summary: "Cập nhật trạng thái sản phẩm",
        description: "Khóa hoặc phê duyệt hiển thị sản phẩm (dành cho Admin).",
        operationId: "updateProductStatus",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "product", in: "path", description: "ID của sản phẩm", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["pending", "active", "hidden", "rejected"], example: "active"),
                    new OA\Property(property: "admin_note", type: "string", example: "Sản phẩm vi phạm chính sách")
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
                        new OA\Property(property: "message", type: "string", example: "Trang thai san pham da duoc cap nhat thanh cong.")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin"),
            new OA\Response(response: 404, description: "Không tìm thấy sản phẩm")
        ]
    )]
    public function updateProductStatus(Product $product, AdminProductStatusRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $status = $request->input('status');
        $product->status = $status;
        $product->admin_note = $status === ProductStatus::Hidden->value
            ? $request->input('admin_note')
            : null;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => $status === ProductStatus::Hidden->value
                ? 'San pham da bi an va gui thong bao canh bao toi chu shop.'
                : 'Trang thai san pham da duoc cap nhat thanh cong.',
            'data' => [
                'product_id' => $product->id,
                'status' => $product->status->value,
            ],
        ], 200);
    }

    #[OA\Get(
        path: "/admin/orders",
        summary: "Xem danh sách tất cả đơn hàng",
        description: "Lấy danh sách lịch sử tất cả các đơn đặt hàng toàn sàn (dành cho Admin).",
        operationId: "listAdminOrders",
        tags: ["Admin"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", description: "Trạng thái đơn hàng", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "shop_id", in: "query", description: "Lọc theo ID Shop", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "start_date", in: "query", description: "Từ ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", description: "Đến ngày (Y-m-d)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", description: "Số lượng bản ghi trên trang", required: false, schema: new OA\Schema(type: "integer", default: 15))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Không có quyền Admin")
        ]
    )]
    public function listOrders(AdminOrderListRequest $request): JsonResponse
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $orders = Order::query()
            ->with(['user:id,name,email', 'items.productVariant.product'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->filled('shop_id'),
                fn ($query) => $query->whereHas(
                    'items.productVariant.product',
                    fn ($subQuery) => $subQuery->where('shop_id', $request->integer('shop_id'))
                )
            )
            ->when(
                $request->input('start_date') ?? $request->input('from_date'),
                fn ($query, $date) => $query->whereDate('created_at', '>=', $date)
            )
            ->when(
                $request->input('end_date') ?? $request->input('to_date'),
                fn ($query, $date) => $query->whereDate('created_at', '<=', $date)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('limit', 15))
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'shop_ids' => $order->items
                    ->map(fn ($item) => $item->productVariant?->product?->shop_id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
            ]);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 200);
    }
}
