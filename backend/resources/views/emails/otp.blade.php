<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OTP</title>
</head>
<body style="font-family: Arial; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background: #fff; border-radius: 10px; overflow: hidden;">
        <!-- Banner -->
        <tr>
            <td>
                <img src="https://res.cloudinary.com/dk6trtfih/image/upload/v1776235658/banner_u2tvei.png" 
                    style="width:100%; display:block;">
            </td>
        </tr>

        <!-- Content -->
        <div style="padding: 30px;">
            <h3 style="font-size:25px; margin-bottom:10px;">Xin chào,</h3>

            <p style="font-size:20px;">Mã xác thực OTP của bạn là:</p>

            <div style="
                font-size: 30px;
                font-weight: bold;
                color: #ff6b00;
                text-align: center;
                margin: 20px 0;
            ">
            {{ $otp }}
            </div>

            <p style="font-size:16px;">Hãy nhập mã này để tiếp tục.</p>

            <p style="font-size:16px;"><strong>Lưu ý:</strong></p>
            <ul style="font-size:16px;">
                <li>Mã có hiệu lực trong 5 phút</li>
                <li>Không chia sẻ mã cho bất kỳ ai</li>
            </ul>

            <p style="font-size:16px; margin-top: 20px;">
                Nếu bạn không yêu cầu, vui lòng bỏ qua email này.
            </p>

            <p style="font-size:16px;">Cảm ơn bạn ❤️</p>
        </div>

        <!-- Footer -->
        <div style="padding: 15px; text-align: center; font-size: 12px; color: #999;">
            Email này không nhận phản hồi
        </div>

    </div>
</body>
</html>