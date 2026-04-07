<?php
session_start();
require_once 'includes/db_connect.php'; // Đảm bảo bạn đã tạo file này như hướng dẫn trước

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Kiểm tra mật khẩu khớp
    if ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra email đã tồn tại chưa
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt_check->execute(['email' => $email]);
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Email này đã được đăng ký!";
        } else {
            // Mã hóa mật khẩu
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert vào database
            $sql = "INSERT INTO users (fullname, email, phone, password) VALUES (:fullname, :email, :phone, :password)";
            $stmt = $pdo->prepare($sql);
            
            try {
                $stmt->execute([
                    ':fullname' => $fullname,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':password' => $hashed_password
                ]);
                $success = "Đăng ký thành công! Vui lòng đăng nhập.";
                // Tùy chọn: header("Location: login.php"); exit;
            } catch (PDOException $e) {
                $error = "Có lỗi xảy ra, vui lòng thử lại: " . $e->getMessage();
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
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2cc0d",
                        "background-light": "#f8f8f5",
                        "background-dark": "#221f10",
                    },
                    fontFamily: {
                        "display": ["Be Vietnam Pro", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <title>Đăng Ký - GOLD CINEMA</title>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen">
    <div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            <div class="flex flex-1 justify-center py-10 px-4 z-10">
                <div class="layout-content-container flex flex-col max-w-[520px] flex-1 bg-primary/5 p-6 md:p-10 rounded-xl border border-primary/10 shadow-2xl backdrop-blur-sm">
                    
                    <div class="flex flex-col items-center mb-8">
                        <h1 class="text-3xl font-black tracking-tighter uppercase italic">H CINEMA</h1>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-100 mb-2">Đăng Ký Tài Khoản</h2>
                        <div class="h-1 w-20 bg-primary rounded-full"></div>
                    </div>

                    <?php if(!empty($error)): ?>
                        <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-lg mb-4 text-sm">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($success)): ?>
                        <div class="bg-green-500/10 border border-green-500/50 text-green-500 px-4 py-3 rounded-lg mb-4 text-sm">
                            <?php echo $success; ?> <a href="login.php" class="font-bold underline">Đăng nhập ngay</a>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php" class="flex flex-col gap-5">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-300">Họ và tên</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/60">person</span>
                                <input name="fullname" required class="w-full pl-10 pr-4 py-3 rounded-lg border border-primary/20 bg-background-dark/50 text-slate-100 focus:border-primary outline-none placeholder:text-slate-500" placeholder="Nhập họ và tên của bạn" type="text" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-300">Email</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/60">mail</span>
                                <input name="email" required class="w-full pl-10 pr-4 py-3 rounded-lg border border-primary/20 bg-background-dark/50 text-slate-100 focus:border-primary outline-none placeholder:text-slate-500" placeholder="example@gmail.com" type="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-300">Số điện thoại</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/60">call</span>
                                <input name="phone" required class="w-full pl-10 pr-4 py-3 rounded-lg border border-primary/20 bg-background-dark/50 text-slate-100 focus:border-primary outline-none placeholder:text-slate-500" placeholder="090x xxx xxx" type="tel" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-300">Mật khẩu</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/60">lock</span>
                                <input name="password" required class="w-full pl-10 pr-10 py-3 rounded-lg border border-primary/20 bg-background-dark/50 text-slate-100 focus:border-primary outline-none placeholder:text-slate-500" placeholder="••••••••" type="password"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-slate-300">Xác nhận mật khẩu</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/60">lock_reset</span>
                                <input name="confirm_password" required class="w-full pl-10 pr-4 py-3 rounded-lg border border-primary/20 bg-background-dark/50 text-slate-100 focus:border-primary outline-none placeholder:text-slate-500" placeholder="••••••••" type="password"/>
                            </div>
                        </div>
                        
                        <button class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-4 rounded-lg transition-all mt-4" type="submit">
                            ĐĂNG KÝ NGAY
                        </button>
                    </form>

                    <div class="mt-8 pt-6 border-t border-primary/10 text-center">
                        <p class="text-slate-400">Đã có tài khoản?</p>
                        <a class="inline-block mt-2 text-primary font-bold hover:underline" href="login.php">Quay lại Đăng nhập</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="fixed inset-0 -z-10 opacity-20 pointer-events-none">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-primary/20 via-transparent to-transparent"></div>
            <div class="absolute w-full h-full bg-cover bg-center" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuD-z54Cljeg9EslpPLsZIEwsiDzFBLbDLt2ag1kDLnzUeBXtLIY46MqHB22mxLuBN1aWuhZzY7o5X_LxPDEPW1d4_0fcq_iqlNxNz0NAOZ1MelYqJhuO0YItn6nFjfHt0RqIyVDAnXxYUOIR4fYdtRmI1fT6CVA-ztC5MvYZcdkhjgVKJvdCxNt0KSQgh2T5I5RC8eK-h8TBy5z3qOpmE-jjD2bLEFQ02xUlLYlkpkyBuuI5LVBSclohhoVy6wXWr99ZdTAuxe8Iuw')"></div>
        </div>
    </div>
</body>
</html>