<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: "/profile",
        summary: "Lấy thông tin hồ sơ người dùng hiện tại",
        description: "Trả về chi tiết thông tin hồ sơ cá nhân, bao gồm cả tổng số tiền đã mua hàng và danh sách sổ địa chỉ.",
        operationId: "getUserProfile",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lấy thông tin thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Lấy thông tin hồ sơ thành công."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Chưa xác thực")
        ]
    )]
    public function show(): JsonResponse
    {
        $user = Auth::user()->load('addresses');

        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin hồ sơ thành công.',
            'data'    => new ProfileResource($user),
        ], 200);
    }

    #[OA\Put(
        path: "/profile",
        summary: "Cập nhật thông tin cá nhân (Email, Họ tên, Số điện thoại)",
        description: "Cho phép người dùng cập nhật lại họ tên, email, và số điện thoại của mình.",
        operationId: "updateUserProfile",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "phone"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Nguyen Van A Mới"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "new_email@example.com"),
                    new OA\Property(property: "phone", type: "string", example: "0987654321")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Cập nhật thành công",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Cập nhật thông tin hồ sơ thành công."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Dữ liệu validation không hợp lệ"),
            new OA\Response(response: 401, description: "Chưa xác thực")
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin hồ sơ thành công.',
            'data'    => new ProfileResource($user->load('addresses')),
        ], 200);
    }

    #[OA\Put(
        path: "/profile/password",
        summary: "Thay đổi mật khẩu cá nhân",
        description: "Người dùng cung cấp mật khẩu cũ và xác thực mật khẩu mới để đổi mật khẩu.",
        operationId: "updateUserPassword",
        tags: ["Profile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "new_password", "new_password_confirmation"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", format: "password", example: "oldpassword123"),
                    new OA\Property(property: "new_password", type: "string", format: "password", example: "newpassword123"),
                    new OA\Property(property: "new_password_confirmation", type: "string", format: "password", example: "newpassword123")
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
                        new OA\Property(property: "message", type: "string", example: "Thay đổi mật khẩu thành công.")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Mật khẩu cũ không đúng hoặc mật khẩu mới validate sai"),
            new OA\Response(response: 401, description: "Chưa xác thực")
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = Auth::user();

        // Kiểm tra mật khẩu hiện tại
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không chính xác.',
            ], 422);
        }

        // Cập nhật mật khẩu mới
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thay đổi mật khẩu thành công.',
        ], 200);
    }
}
