<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Enums\Purpose;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;

class AuthService
{

    /**
     * Bước 1: Đăng ký thông tin và phát hành mã OTP băm (Hash) gửi qua Email
     */
    public function registerPendingUser(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            // 1. Tạo tài khoản User ở trạng thái chờ kích hoạt
            $user = User::query()->create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'phone'             => $data['phone'],
                'password'          => Hash::make($data['password']),
                'role'              => \App\Enums\UserRole::Buyer, 
                'status'            => \App\Enums\UserStatus::Pending, 
                'email_verified_at' => null, 
            ]);

            // 2. Tạo mã OTP ngẫu nhiên 6 số để gửi cho khách
            $otpCode = (string) rand(100000, 999999);

            // 3. Xóa các mã OTP cũ của User này nếu có
            DB::table('otp_verifications')
                ->where('user_id', $user->id)
                ->where('purpose', Purpose::UserRegistration->value) 
                ->delete();
            
            // 4. Lưu vào DB: Mã hóa OTP thành code_hash 
            DB::table('otp_verifications')->insert([
                'user_id'       => $user->id,
                'code_hash'     => Hash::make($otpCode), // 💡 Băm mã hóa bảo mật
                'purpose'       => Purpose::UserRegistration->value,       
                'expires_at'    => now()->addMinutes(5),
                'created_at'    => now(), 
                'attempt_count' => 0,
            ]);

            // 5. Gửi Email chứa mã gốc (chưa băm) thì khách mới đọc được
            Mail::to($data['email'])->send(new SendOtpMail($otpCode));

            return true;
        });
    }

    /**
     * Bước 2: Kiểm tra OTP băm, kích hoạt tài khoản và cấp Token Đăng nhập
     */
    public function verifyOtpAndActivate(array $data): string
    {
        $email   = $data['email'];
        $otpCode = $data['otp_code'];

        // 1. Tìm User dựa vào Email trước để lấy user_id
        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            throw new Exception('Không tìm thấy tài khoản người dùng.', 404);
        }

        // 2. Lấy danh sách các mã OTP chưa dùng và chưa hết hạn của User này
        $verifications = DB::table('otp_verifications')
            ->where('user_id', $user->id)
            ->where('purpose', Purpose::UserRegistration->value)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get();

        $validOtp = null;

        // 3. Vì mã trong DB đã bị băm (Hash::make), ta phải dùng vòng lặp Hash::check để dò tìm
        foreach ($verifications as $verification) {
            if (Hash::check($otpCode, $verification->code_hash)) {
                $validOtp = $verification;
                break;
            }
        }

        if (!$validOtp) {
            // Tăng số lần nhập sai lên để chặn phá hoại
            DB::table('otp_verifications')
                ->where('user_id', $user->id)
                ->where('purpose', 'verification')
                ->increment('attempt_count');

            throw new Exception('Mã OTP không chính xác hoặc đã hết hạn.', 400);
        }

        // 4. Kích hoạt tài khoản người dùng
        $user->update([
            'email_verified_at' => now(),
            'status'            => 'active' // Đổi trạng thái sang hoạt động (hoặc số 1 tùy nhóm bạn quy định)
        ]);

        // 5. Đánh dấu mã OTP này đã được sử dụng thành công
        DB::table('otp_verifications')
            ->where('id', $validOtp->id)
            ->update([
                'used_at' => now()
            ]);

        // 6. Tạo Token Đăng Nhập tự động cho người dùng (Sanctum)
        return $user->createToken('auth_token')->plainTextToken;
    }

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
