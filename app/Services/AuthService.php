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
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthService
{

    // -- Đăng kí tài khoản --
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

    // -- Quên mật khẩu --
    /**
     * Bước 1: Gửi mã OTP quên mật khẩu qua Email
     */
    public function sendPasswordResetOtp(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->where('email', $data['email'])->first();
            if (!$user) {
                throw new Exception('Tài khoản không tồn tại.', 404);
            }

            // Tạo mã OTP ngẫu nhiên 6 số
            $otpCode = (string) rand(100000, 999999);

            // Dọn dẹp các mã OTP quên mật khẩu cũ của user này để tránh xung đột
            DB::table('otp_verifications')
                ->where('user_id', $user->id)
                ->where('purpose', Purpose::PasswordForgot->value)
                ->delete();

            // Lưu OTP đã băm (Hash) vào bảng theo cấu trúc bảo mật của nhóm
            DB::table('otp_verifications')->insert([
                'user_id'       => $user->id,
                'code_hash'     => Hash::make($otpCode),
                'purpose'       => Purpose::PasswordForgot->value,
                'expires_at'    => now()->addMinutes(5),
                'created_at'    => now(),
                'attempt_count' => 0,
            ]);

            // Bắn thư đi ngay lập tức
            Mail::to($user->email)->send(new ForgotPasswordMail($otpCode));

            return true;
        });
    }

    /**
     * Bước 2: Xác thực mã OTP và cấp Token đổi mật khẩu tạm thời
     */
    public function verifyPasswordResetOtp(array $data): string
    {
        $user = User::query()->where('email', $data['email'])->first();
        if (!$user) {
            throw new Exception('Tài khoản không tồn tại.', 404);
        }

        // Lấy các bản ghi OTP quên mật khẩu còn hạn ra soát mã băm
        $verifications = DB::table('otp_verifications')
            ->where('user_id', $user->id)
            ->where('purpose', Purpose::PasswordForgot->value)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get();

        $validOtp = null;
        foreach ($verifications as $verification) {
            if (Hash::check($data['otp_code'], $verification->code_hash)) {
                $validOtp = $verification;
                break;
            }
        }

        if (!$validOtp) {
            DB::table('otp_verifications')
                ->where('user_id', $user->id)
                ->where('purpose', Purpose::PasswordForgot->value)
                ->increment('attempt_count');

            throw new Exception('Mã OTP không chính xác hoặc đã hết hạn.', 400);
        }

        // Đánh dấu mã OTP đã được sử dụng
        DB::table('otp_verifications')
            ->where('id', $validOtp->id)
            ->update(['used_at' => now()]);

        // Sinh một chuỗi token ngẫu nhiên đại diện cho phiên làm việc an toàn
        $resetToken = Str::random(60);

        // Lưu token kèm ID của user vào Cache hệ thống trong 15 phút
        Cache::put('password_reset_token_' . $resetToken, $user->id, now()->addMinutes(15));

        return $resetToken;
    }

    /**
     * Bước 3: Kiểm tra phiên làm việc và thực hiện cập nhật mật khẩu mới
     */
    public function resetPassword(array $data): bool
    {
        $cacheKey = 'password_reset_token_' . $data['reset_token'];

        // Đọc ID người dùng từ Cache ra để nhận diện danh tính
        $userId = Cache::get($cacheKey);
        if (!$userId) {
            throw new Exception('Phiên xác thực đã hết hạn hoặc không hợp lệ. Vui lòng lấy lại mã OTP.', 400);
        }

        // Tìm và cập nhật mật khẩu thật sự của User dưới DB
        $user = User::query()->find($userId);
        if (!$user) {
            throw new Exception('Không tìm thấy tài khoản người dùng tương ứng.', 404);
        }

        $user->update([
            'password' => Hash::make($data['password'])
        ]);

        // Xóa mã token khỏi Cache ngay sau khi dùng xong để ngăn chặn dùng lại (Replay Attack)
        Cache::forget($cacheKey);

        return true;
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
