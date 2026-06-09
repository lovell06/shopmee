<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAddressRequest;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use OpenApi\Attributes as OA;

class AddressController extends Controller
{
    protected AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    #[OA\Get(
        path: "/addresses",
        summary: "Lấy danh sách địa chỉ của User",
        description: "Lấy danh sách địa chỉ giao hàng của Buyer đang đăng nhập.",
        operationId: "getAddresses",
        tags: ["Addresses"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            )
        ]
    )]
    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $addresses = $this->addressService->getAddresses($userId);

            return response()->json([
                'success' => true,
                'data'    => $addresses
            ], 200);
        } catch (Exception $e) {
            Log::error('Lỗi API lấy danh sách địa chỉ: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Không thể tải danh sách địa chỉ.'], 500);
        }
    }

    #[OA\Post(
        path: "/addresses/add",
        summary: "Thêm địa chỉ mới",
        description: "Thêm địa chỉ giao hàng mới cho Buyer đang đăng nhập.",
        operationId: "createAddress",
        tags: ["Addresses"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["receiver_name", "receiver_phone", "province", "district", "ward", "specific_address"],
                properties: [
                    new OA\Property(property: "receiver_name", type: "string", example: "Nguyen Van A"),
                    new OA\Property(property: "receiver_phone", type: "string", example: "0987654321"),
                    new OA\Property(property: "province", type: "string", example: "Hồ Chí Minh"),
                    new OA\Property(property: "district", type: "string", example: "Quận 1"),
                    new OA\Property(property: "ward", type: "string", example: "Phường Bến Nghé"),
                    new OA\Property(property: "specific_address", type: "string", example: "123 Nguyễn Huệ"),
                    new OA\Property(property: "is_default", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đã thêm địa chỉ mới vào sổ địa chỉ thành công!")
                    ]
                )
            )
        ]
    )]
    public function store(StoreAddressRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $this->addressService->createAddress($userId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm địa chỉ mới vào sổ địa chỉ thành công!'
            ], 201);
        } catch (Exception $e) {
            Log::error('Lỗi API thêm địa chỉ: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi hệ thống, không thể thêm địa chỉ.'], 500);
        }
    }

    #[OA\Put(
        path: "/addresses/{id}",
        summary: "Cập nhật địa chỉ",
        description: "Cập nhật thông tin chi tiết của địa chỉ dựa theo ID.",
        operationId: "updateAddress",
        tags: ["Addresses"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của địa chỉ cần cập nhật (UUID hoặc String ID)", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["receiver_name", "receiver_phone", "province", "district", "ward", "specific_address"],
                properties: [
                    new OA\Property(property: "receiver_name", type: "string", example: "Nguyen Van A"),
                    new OA\Property(property: "receiver_phone", type: "string", example: "0987654321"),
                    new OA\Property(property: "province", type: "string", example: "Hồ Chí Minh"),
                    new OA\Property(property: "district", type: "string", example: "Quận 1"),
                    new OA\Property(property: "ward", type: "string", example: "Phường Bến Nghé"),
                    new OA\Property(property: "specific_address", type: "string", example: "123 Nguyễn Huệ Cập Nhật"),
                    new OA\Property(property: "is_default", type: "boolean", example: true)
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
                        new OA\Property(property: "message", type: "string", example: "Cập nhật địa chỉ thành công!")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Địa chỉ không tồn tại"
            )
        ]
    )]
    public function update(StoreAddressRequest $request, string $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            $this->addressService->updateAddress($id, $userId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật địa chỉ thành công!'
            ], 200);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $statusCode === 500 ? 'Không thể cập nhật địa chỉ lúc này.' : $e->getMessage()
            ], $statusCode);
        }
    }

    #[OA\Delete(
        path: "/addresses/{id}",
        summary: "Xóa địa chỉ",
        description: "Xóa một địa chỉ khỏi sổ địa chỉ của Buyer.",
        operationId: "deleteAddress",
        tags: ["Addresses"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "ID của địa chỉ cần xóa", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Xóa thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đã xóa địa chỉ thành công.")
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Địa chỉ không tồn tại"
            )
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            $this->addressService->deleteAddress($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa địa chỉ thành công.'
            ], 200);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $statusCode === 500 ? 'Không thể xóa địa chỉ.' : $e->getMessage()
            ], $statusCode);
        }
    }
}
