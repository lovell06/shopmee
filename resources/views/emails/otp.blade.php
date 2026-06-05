<!DOCTYPE html>
<html>
<head>
    <title>Xác thực OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 500px; background: #fff; padding: 20px; border-radius: 8px; margin: 0 auto;">
        <h2 style="color: #ee4d2d; text-align: center;">Chào mừng bạn đến với Shopmee!</h2>
        <p>Cảm ơn bạn đã đăng ký tài khoản. Vui lòng sử dụng mã OTP dưới đây để hoàn tất kích hoạt tài khoản:</p>
        <div style="background: #f8f9fa; font-size: 24px; font-weight: bold; text-align: center; padding: 15px; letter-spacing: 5px; color: #333; margin: 20px 0; border: 1px dashed #ee4d2d;">
            {{ $otpCode }}
        </div>
        <p style="color: #666; font-size: 12px;">Mã OTP này có hiệu lực trong vòng 5 phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.</p>
    </div>
</body>
</html>