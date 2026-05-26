<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;

class AuthService
{
    /**
     * Xu ly logic dang nhap he thong
     */
    public function login(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new Exception('Email hoac mat khau khong chinh xac.', 401);
        }

        if ($user->status === UserStatus::Blocked) {
            throw new Exception('Tai khoan cua ban da bi khoa. Vui long lien he quan tri vien.', 403);
        }

        $token = $user->createToken('access_token', [$user->role->value])->plainTextToken;

        return [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Xu ly logic dang xuat
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
