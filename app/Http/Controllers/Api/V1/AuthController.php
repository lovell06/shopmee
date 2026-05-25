<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthController extends Controller
{
    // Inject AuthService trực tiếp vào Constructor một cách ngắn gọn
    public function __construct(protected AuthService $authService) 
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Gọi dịch vụ xử lý đăng nhập bằng dữ liệu đã validate
            $result = $this->authService->login($request->validated());

            // Trả về dữ liệu thành công (HTTP 200)
            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công!',
                'data'    => [
                    'access_token' => $result['access_token'],
                    'token_type'   => $result['token_type'],
                    'user'         => [
                        'id'    => $result['user']->id,
                        'name'  => $result['user']->name,
                        'email' => $result['user']->email,
                        'role'  => $result['user']->role, // TRẢ VỀ ROLE ĐỂ FRONTEND ĐIỀU HƯỚNG
                    ]
                ]
            ], 200);

        } catch (Exception $e) {
            // Xác định mã lỗi định dạng từ Service quăng ra (401 là sai thông tin)
            $statusCode = $e->getCode() === 401 ? 401 : 500;
            
            if ($statusCode === 500) {
                Log::error('Lỗi hệ thống đăng nhập: ' . $e->getMessage());
                $message = 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau!';
            } else {
                $message = $e->getMessage();
            }

            return response()->json([
                'success' => false,
                'message' => $message
            ], $statusCode);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            // Lấy thông tin user hiện tại đang đăng nhập qua token
            $user = auth()->user();

            // Gọi service xử lý xóa token
            $this->authService->logout($user);

            return response()->json([
                'success' => true,
                'message' => 'Đăng xuất thành công!'
            ], 200);

        } catch (Exception $e) {
            Log::error('Lỗi hệ thống đăng xuất: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau!'
            ], 500);
        }
    }
}
