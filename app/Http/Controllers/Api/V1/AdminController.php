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
