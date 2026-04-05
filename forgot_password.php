<?php
session_start();
require_once 'includes/db_connect.php';

$error = '';
$success = '';

// Bước hiện tại của form: 1 (Nhập Email), 2 (Nhập OTP), 3 (Tạo mật khẩu mới)
$step = $_SESSION['reset_step'] ?? 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // XỬ LÝ BƯỚC 1: GỬI MÃ OTP VÀO EMAIL
    if ($action === 'send_otp') {
        $email = trim($_POST['email']);
        
        // Kiểm tra email có tồn tại không
        $stmt = $pdo->prepare("SELECT fullname FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Email này không tồn tại trong hệ thống!";
        } else {
            // Tạo OTP 6 số ngẫu nhiên
            $otp = sprintf("%06d", mt_rand(1, 999999));
            
            // Gọi thư viện PHPMailer
            require 'includes/PHPMailer/Exception.php';
            require 'includes/PHPMailer/PHPMailer.php';
            require 'includes/PHPMailer/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                // Cấu hình SMTP (ĐIỀN THÔNG TIN CỦA BẠN VÀO ĐÂY)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'qha1905@gmail.com'; // ĐỔI LẠI GMAIL CỦA BẠN
                $mail->Password   = 'fore smdu fpsu mciy'; // ĐỔI LẠI MK ỨNG DỤNG
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('qha1905@gmail.com', 'Gold Cinema Support'); // ĐỔI LẠI GMAIL
                $mail->addAddress($email, $user['fullname']);

                $mail->isHTML(true);
                $mail->Subject = 'Mã xác nhận khôi phục mật khẩu - Gold Cinema';
                
                $email_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; text-align: center;'>
                    <h2 style='color: #f2cc0d; background: #1a180a; padding: 10px; border-radius: 8px;'>GOLD CINEMA</h2>
                    <p>Xin chào <strong>{$user['fullname']}</strong>,</p>
                    <p>Chúng tôi nhận được yêu cầu khôi phục mật khẩu cho tài khoản của bạn.</p>
                    <p>Mã xác nhận (OTP) của bạn là:</p>
                    <h1 style='font-size: 32px; letter-spacing: 5px; color: #d32f2f; background: #f5f5f5; padding: 15px; border-radius: 8px; display: inline-block;'>{$otp}</h1>
                    <p style='color: #777; font-size: 12px; margin-top: 20px;'>Mã này sẽ hết hạn trong vòng 5 phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.</p>
                </div>";

                $mail->Body = $email_body;
                $mail->send();

                // Lưu Session để xác thực ở Bước 2
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_expiry'] = time() + 300; // Hết hạn sau 5 phút (300 giây)
                
                $_SESSION['reset_step'] = 2; // Chuyển sang form nhập OTP
                $step = 2;
                $success = "Mã OTP đã được gửi đến email của bạn!";

            } catch (Exception $e) {
                $error = "Hệ thống đang lỗi, không thể gửi email lúc này. Vui lòng thử lại sau.";
                error_log("Lỗi gửi OTP: {$mail->ErrorInfo}");
            }
        }
    }

    // XỬ LÝ BƯỚC 2: XÁC THỰC MÃ OTP
    elseif ($action === 'verify_otp') {
        $user_otp = trim($_POST['otp']);

        if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_expiry'])) {
            $error = "Phiên giao dịch đã hết hạn. Vui lòng làm lại từ đầu.";
            $_SESSION['reset_step'] = 1;
            $step = 1;
        } elseif (time() > $_SESSION['reset_expiry']) {
            $error = "Mã OTP đã hết hạn! Vui lòng gửi lại mã mới.";
            unset($_SESSION['reset_otp']);
        } elseif ($user_otp !== $_SESSION['reset_otp']) {
            $error = "Mã OTP không chính xác!";
        } else {
            // Xác thực đúng -> Chuyển sang form đặt mật khẩu mới
            $_SESSION['reset_step'] = 3;
            $step = 3;
            $success = "Xác thực thành công! Vui lòng nhập mật khẩu mới.";
        }
    }

    // XỬ LÝ BƯỚC 3: ĐẶT LẠI MẬT KHẨU MỚI
    elseif ($action === 'reset_password') {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Mật khẩu nhập lại không khớp!";
        } elseif (strlen($new_password) < 6) {
            $error = "Mật khẩu phải có ít nhất 6 ký tự.";
        } else {
            // Mã hóa mật khẩu và lưu vào DB
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $email = $_SESSION['reset_email'];

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            if ($stmt->execute([$hashed_password, $email])) {
                // Xóa toàn bộ Session liên quan đến Reset
                unset($_SESSION['reset_step']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_expiry']);

                // Chuyển về trang login và báo thành công
                header("Location: login.php?msg=reset_success");
                exit;
            } else {
                $error = "Đã xảy ra lỗi khi cập nhật mật khẩu.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Khôi phục mật khẩu - GOLD CINEMA</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@100;300;400;500;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#221f10" }, fontFamily: {"display": ["Be Vietnam Pro", "sans-serif"]} } } }
    </script>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased selection:bg-primary/30">
    <div class="relative min-h-screen flex flex-col items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 bg-gradient-to-t from-background-dark via-background-dark/80 to-transparent z-10"></div>
            <div class="w-full h-full bg-cover bg-center opacity-40" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCFSk4Gg1CMkLG55fNUMl5wtcO09F8KnnRJqJiPvt9tNKJRPtpcAma41RGzWb1ML62kIlYaNbMVl-bnxKV5ehyvbCk8u8LQ9bAggaUtTOlTQE7tbAXXKZ6-0ZG4RzeZ82d661Qi7YAxGhEvOQ-AMODhVv3dCE23T5NJyhlrQtS7Xyi74xezTc9B70srBVmaUOmRL4H22CqqIslUVhsKS6iGXb6jKruuD4bweECMpPlFVK80jTITaY4_hN8qZFrPlRh0ngaDe2vw4Ok');"></div>
        </div>
        
        <div class="relative z-20 w-full max-w-md px-6 py-12">
            <div class="flex flex-col items-center mb-10">
                <a href="index.php" class="flex items-center gap-3 text-primary mb-2 hover:scale-105 transition-transform">
                    <span class="material-symbols-outlined text-5xl">lock_reset</span>
                    <h1 class="text-3xl font-black tracking-tighter uppercase italic">GOLD CINEMA</h1>
                </a>
            </div>
            
            <div class="bg-slate-900/50 backdrop-blur-xl border border-primary/20 rounded-xl p-8 shadow-2xl">
                
                <div class="mb-8 text-center">
                    <h2 class="text-2xl font-bold text-slate-100">
                        <?php 
                            if($step == 1) echo "Khôi phục mật khẩu";
                            elseif($step == 2) echo "Xác thực OTP";
                            else echo "Tạo mật khẩu mới";
                        ?>
                    </h2>
                    <p class="text-slate-400 text-sm mt-2">
                        <?php 
                            if($step == 1) echo "Nhập email của bạn để nhận mã xác nhận.";
                            elseif($step == 2) echo "Vui lòng kiểm tra hộp thư <strong class='text-primary'>".$_SESSION['reset_email']."</strong> và nhập mã 6 số.";
                            else echo "Nhập mật khẩu mới an toàn cho tài khoản của bạn.";
                        ?>
                    </p>
                </div>

                <?php if(!empty($success)): ?>
                    <div class="bg-green-500/10 border border-green-500/50 text-green-500 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 font-bold">
                        <span class="material-symbols-outlined">check_circle</span> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined">error</span> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="forgot_password.php" class="space-y-5">
                    
                    <?php if ($step == 1): ?>
                        <input type="hidden" name="action" value="send_otp">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-300 ml-1">Email đã đăng ký</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xl">mail</span>
                                <input name="email" required type="email" class="w-full bg-background-dark/50 border border-slate-700 focus:border-primary focus:ring-1 focus:ring-primary rounded-lg py-3.5 pl-12 pr-4 text-slate-100 placeholder:text-slate-600 outline-none" placeholder="example@email.com"/>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-4 rounded-lg shadow-lg shadow-primary/20 transition-all transform active:scale-[0.98] mt-2">
                            GỬI MÃ XÁC NHẬN
                        </button>
                    <?php endif; ?>

                    <?php if ($step == 2): ?>
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-300 ml-1 text-center">Nhập mã OTP 6 số</label>
                            <input name="otp" required type="text" maxlength="6" class="w-full bg-background-dark/50 border border-slate-700 focus:border-primary focus:ring-1 focus:ring-primary rounded-lg py-4 px-4 text-slate-100 text-center text-3xl font-black tracking-[1em] outline-none" placeholder="------"/>
                        </div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-4 rounded-lg shadow-lg shadow-primary/20 transition-all transform active:scale-[0.98] mt-2">
                            XÁC THỰC MÃ OTP
                        </button>
                        
                        <div class="text-center mt-4">
                            <a href="forgot_password.php?action=reset" onclick="<?php unset($_SESSION['reset_step']); ?>" class="text-slate-400 hover:text-primary text-sm transition-colors">Dùng email khác?</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($step == 3): ?>
                        <input type="hidden" name="action" value="reset_password">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-300 ml-1">Mật khẩu mới</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xl">lock</span>
                                <input name="new_password" required type="password" minlength="6" class="w-full bg-background-dark/50 border border-slate-700 focus:border-primary focus:ring-1 focus:ring-primary rounded-lg py-3.5 pl-12 pr-4 text-slate-100 placeholder:text-slate-600 outline-none" placeholder="Ít nhất 6 ký tự"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-slate-300 ml-1">Nhập lại mật khẩu mới</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xl">lock_clock</span>
                                <input name="confirm_password" required type="password" minlength="6" class="w-full bg-background-dark/50 border border-slate-700 focus:border-primary focus:ring-1 focus:ring-primary rounded-lg py-3.5 pl-12 pr-4 text-slate-100 placeholder:text-slate-600 outline-none" placeholder="Nhập lại mật khẩu ở trên"/>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-4 rounded-lg shadow-lg shadow-primary/20 transition-all transform active:scale-[0.98] mt-2">
                            LƯU MẬT KHẨU MỚI
                        </button>
                    <?php endif; ?>

                </form>

            </div>
            
            <div class="mt-8 flex justify-center gap-6">
                <a class="text-slate-500 hover:text-slate-300 text-xs transition-colors flex items-center gap-1" href="login.php">
                    <span class="material-symbols-outlined text-[14px]">arrow_back</span> Quay lại đăng nhập
                </a>
            </div>
        </div>
    </div>
</body>
</html>