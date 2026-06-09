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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    #[OA\Post(
        path: "/register",
        summary: "Đăng ký tài khoản người dùng mới (chưa kích hoạt)",
        description: "Đăng ký thông tin tài khoản người dùng ban đầu. Sau khi thành công, hệ thống sẽ gửi mã OTP xác thực qua email.",
        operationId: "registerUser",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation", "phone"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Nguyen Van A"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "nva@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "12345678"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "12345678"),
                    new OA\Property(property: "phone", type: "string", example: "0987654321")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Đăng ký thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Đăng ký thông tin thành công! Vui lòng kiểm tra hộp thư Email để lấy mã OTP xác thực.")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Lỗi validation (Ví dụ: Trùng Email, sai định dạng điện thoại...)"
            ),
            new OA\Response(
                response: 500,
                description: "Lỗi hệ thống hoặc lỗi gửi mail"
            )
        ]
    )]
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
        }
    }

    #[OA\Post(
        path: "/register/verify",
        summary: "Kích hoạt tài khoản bằng mã OTP",
        description: "Nhập mã OTP được gửi qua email để hoàn tất việc đăng ký và nhận token đăng nhập.",
        operationId: "verifyRegisterOtp",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "otp_code"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "nva@example.com"),
                    new OA\Property(property: "otp_code", type: "string", example: "123456")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Kích hoạt thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Tài khoản của bạn đã được kích hoạt thành công!"),
                        new OA\Property(property: "access_token", type: "string", example: "1|abcdefghijklmnop..."),
                        new OA\Property(property: "token_type", type: "string", example: "Bearer")
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Mã OTP không chính xác hoặc hết hạn"
            )
        ]
    )]
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

    #[OA\Post(
        path: "/auth/login",
        summary: "Đăng nhập người dùng",
        description: "Đăng nhập bằng Email và Password, trả về Access Token.",
        operationId: "loginUser",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "nva@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "12345678")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Đăng nhập thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Dang nhap thanh cong!"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "access_token", type: "string", example: "1|abcdef..."),
                                new OA\Property(property: "token_type", type: "string", example: "Bearer"),
                                new OA\Property(
                                    property: "user",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "Nguyen Van A"),
                                        new OA\Property(property: "email", type: "string", example: "nva@example.com"),
                                        new OA\Property(property: "role", type: "string", example: "buyer")
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Thông tin đăng nhập không chính xác"
            )
        ]
    )]
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

    #[OA\Post(
        path: "/password/forgot",
        summary: "Gửi yêu cầu khôi phục mật khẩu (Quên mật khẩu)",
        description: "Gửi mã OTP khôi phục mật khẩu tới địa chỉ Email đã đăng ký.",
        operationId: "forgotPassword",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "nva@example.com")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Gửi thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Hệ thống đã gửi mã OTP khôi phục mật khẩu vào Email của bạn.")
                    ]
                )
            )
        ]
    )]
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

    #[OA\Post(
        path: "/password/verify",
        summary: "Kiểm tra mã OTP quên mật khẩu",
        description: "Xác minh mã OTP khôi phục mật khẩu được gửi tới email. Trả về reset_token để đổi mật khẩu.",
        operationId: "verifyPasswordOtp",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "otp_code"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "nva@example.com"),
                    new OA\Property(property: "otp_code", type: "string", example: "123456")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Xác minh thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Mã OTP chính xác. Vui lòng chuyển hướng sang trang đổi mật khẩu mới."),
                        new OA\Property(property: "reset_token", type: "string", example: "reset_token_hash_value")
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Mã OTP không đúng"
            )
        ]
    )]
    public function verifyPasswordOtp(VerifyPasswordOtpRequest $request): JsonResponse
    {
        try {
            $resetToken = $this->authService->verifyPasswordResetOtp($request->validated());

            return response()->json([
                'success'     => true,
                'message'     => 'Mã OTP chính xác. Vui lòng chuyển hướng sang trang đổi mật khẩu mới.',
                'reset_token' => $resetToken
            ], 200);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    #[OA\Post(
        path: "/password/reset",
        summary: "Đặt lại mật khẩu mới",
        description: "Sử dụng reset_token để đổi mật khẩu mới.",
        operationId: "resetPassword",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["reset_token", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "reset_token", type: "string", example: "reset_token_hash_value"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "newpassword123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "newpassword123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Đổi mật khẩu thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Chúc mừng! Mật khẩu của bạn đã được thay đổi thành công. Hãy đăng nhập lại.")
                    ]
                )
            )
        ]
    )]
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

    #[OA\Post(
        path: "/auth/logout",
        summary: "Đăng xuất tài khoản",
        description: "Thu hồi Token hiện tại để đăng xuất.",
        operationId: "logoutUser",
        tags: ["Authentication"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Đăng xuất thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Dang xuat thanh cong!")
                    ]
                )
            )
        ]
    )]
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
