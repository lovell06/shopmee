<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\VerifyPasswordOtpRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Services\AuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    // -- Đăng ký --
    /**
     * API Gửi đơn đăng ký thông tin ban đầu
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $this->authService->registerPendingUser($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký thông tin thành công! Vui lòng kiểm tra hộp thư Email để lấy mã OTP xác thực.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gặp sự cố trong quá trình gửi mail đăng ký. Vui lòng thử lại sau.'
            ], 500);
            // return response()->json([
            //     'success' => false,
            //     'message' => $e->getMessage(),
            //     'file'    => $e->getFile(),
            //     'line'    => $e->getLine()
            // ], 500);
        }
    }

    /**
     * API Nhập OTP để kích hoạt tài khoản hoàn toàn
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->verifyOtpAndActivate($request->validated());

            return response()->json([
                'success'      => true,
                'message'      => 'Tài khoản của bạn đã được kích hoạt thành công!',
                'access_token' => $token,
                'token_type'   => 'Bearer'
            ], 200);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    // -- Đăng nhập --
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Dang nhap thanh cong!',
                'data' => [
                    'access_token' => $result['access_token'],
                    'token_type' => $result['token_type'],
                    'user' => [
                        'id' => $result['user']->id,
                        'name' => $result['user']->name,
                        'email' => $result['user']->email,
                        'role' => $result['user']->role,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [401, 403], true) ? $e->getCode() : 500;

            if ($statusCode === 500) {
                Log::error('Loi he thong dang nhap: ' . $e->getMessage());

                $message = 'He thong dang gap su co. Vui long thu lai sau!';
            } else {
                $message = $e->getMessage();
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $statusCode);
        }
    }

    // -- Quên mật khẩu --
    /**
     * API Bước 1: Gửi yêu cầu quên mật khẩu
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->sendPasswordResetOtp($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Hệ thống đã gửi mã OTP khôi phục mật khẩu vào Email của bạn.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Bước 2: Kiểm tra OTP quên mật khẩu
     */
    public function verifyPasswordOtp(VerifyPasswordOtpRequest $request): JsonResponse
    {
        try {
            $resetToken = $this->authService->verifyPasswordResetOtp($request->validated());

            return response()->json([
                'success'     => true,
                'message'     => 'Mã OTP chính xác. Vui lòng chuyển hướng sang trang đổi mật khẩu mới.',
                'reset_token' => $resetToken // Trả token này về cho Frontend giữ chân
            ], 200);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    /**
     * API Bước 3: Đặt lại mật khẩu mới hoàn toàn
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Chúc mừng! Mật khẩu của bạn đã được thay đổi thành công. Hãy đăng nhập lại.'
            ], 200);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    // -- Đăng xuất --
    public function logout(): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->authService->logout($user);

            return response()->json([
                'success' => true,
                'message' => 'Dang xuat thanh cong!',
            ], 200);
        } catch (Exception $e) {
            Log::error('Loi he thong dang xuat: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'He thong dang gap su co. Vui long thu lai sau!',
            ], 500);
        }
    }
}
