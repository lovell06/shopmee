<!DOCTYPE html>
<html>
<head>
    <title>Khôi phục mật khẩu</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 500px; background: #fff; padding: 20px; border-radius: 8px; margin: 0 auto;">
        <h2 style="color: #ee4d2d; text-align: center;">Khôi Phục Mật Khẩu Shopmee</h2>
        <p>Chúng tôi nhận được yêu cầu khôi phục mật khẩu từ bạn. Vui lòng sử dụng mã OTP dưới đây để tiến hành thiết lập lại mật khẩu mới:</p>
        <div style="background: #f8f9fa; font-size: 24px; font-weight: bold; text-align: center; padding: 15px; letter-spacing: 5px; color: #ee4d2d; margin: 20px 0; border: 1px dashed #ee4d2d;">
            {{ $otpCode }}
        </div>
        <p style="color: #666; font-size: 12px;">Mã OTP này có hiệu lực trong vòng 5 phút. Nếu bạn không yêu cầu khôi phục mật khẩu, vui lòng bỏ qua email này.</p>
    </div>
</body>
</html>