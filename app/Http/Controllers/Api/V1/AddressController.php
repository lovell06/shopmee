<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAddressRequest;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class AddressController extends Controller
{
    protected AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    /**
     * Lấy danh sách địa chỉ của User
     */
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

    /**
     * Thêm địa chỉ mới
     */
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

    /**
     * Cập nhật địa chỉ
     */
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

    /**
     * Xóa địa chỉ
     */
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
