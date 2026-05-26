<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Services\AuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

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
