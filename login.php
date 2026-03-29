<?php

// Cấu hình API Google
$google_client_id = '277444230924-6b00s794lm9sg1e5hp5sfupu4v5te5s0.apps.googleusercontent.com';
$google_redirect_uri = 'http://goldcinema.wuaze.com/google_callback.php';

// Tạo link Đăng nhập Google
$google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $google_client_id,
    'redirect_uri' => $google_redirect_uri,
    'response_type' => 'code',
    'scope' => 'email profile'
]);

session_start();
require_once 'includes/db_connect.php';

$error = '';

// Kiểm tra nếu người dùng đã đăng nhập thì đẩy về trang chủ luôn
if (isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"] === true) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Lưu các thông tin cơ bản
        $_SESSION["user_logged_in"] = true;
        $_SESSION["user_id"] = $user['id'];
        $_SESSION["user_name"] = $user['fullname'];
        
        // THÊM DÒNG NÀY ĐỂ LƯU QUYỀN (ROLE)
        $_SESSION["role"] = $user['role']; 
        
        // CHUYỂN HƯỚNG THÔNG MINH:
        // Nếu là admin thì cho bay thẳng vào trang Quản trị, ngược lại về Trang chủ
        if ($_SESSION["role"] === 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Email hoặc mật khẩu không chính xác!";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Đăng nhập - GOLD CINEMA</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@100;300;400;500;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
                    fontFamily: {"display": ["Be Vietnam Pro", "sans-serif"]},
                },
            },
        }
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
                    <span class="material-symbols-outlined text-5xl">theaters</span>
                    <h1 class="text-3xl font-black tracking-tighter uppercase italic">GOLD CINEMA</h1>
                </a>
                <p class="text-slate-400 text-sm font-medium tracking-widest uppercase">Trải nghiệm điện ảnh đẳng cấp</p>
            </div>
            
            <div class="bg-slate-900/50 backdrop-blur-xl border border-primary/20 rounded-xl p-8 shadow-2xl">
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-slate-100">Chào mừng trở lại</h2>
                    <p class="text-slate-400 text-sm mt-1">Đăng nhập để đặt vé và nhận ưu đãi đặc quyền</p>
                </div>

                <?php if(!empty($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-lg mb-6 text-sm">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-5">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-300 ml-1">Email</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xl">mail</span>
                            <input name="email" required class="w-full bg-background-dark/50 border border-slate-700 focus:border-primary focus:ring-1 focus:ring-primary rounded-lg py-3.5 pl-12 pr-4 text-slate-100 placeholder:text-slate-600 outline-none" placeholder="example@email.com" type="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"/>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-slate-300 ml-1">Mật khẩu</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xl">lock</span>
                            <input name="password" required class="w-full bg-background-dark/50 border border-slate-700 focus:border-primary focus:ring-1 focus:ring-primary rounded-lg py-3.5 pl-12 pr-12 text-slate-100 placeholder:text-slate-600 outline-none" placeholder="••••••••" type="password"/>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input class="w-4 h-4 rounded border-slate-700 bg-background-dark text-primary focus:ring-primary focus:ring-offset-background-dark" type="checkbox"/>
                            <span class="text-slate-400 group-hover:text-slate-200 transition-colors">Ghi nhớ đăng nhập</span>
                        </label>
                        <a class="text-primary hover:text-primary/80 font-medium transition-colors" href="#">Quên mật khẩu?</a>
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-4 rounded-lg shadow-lg shadow-primary/20 transition-all transform active:scale-[0.98] mt-2">
                        ĐĂNG NHẬP NGAY
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-center gap-4">
                    <div class="flex-1 border-t border-accent-dark"></div>
                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Hoặc đăng nhập với</span>
                    <div class="flex-1 border-t border-accent-dark"></div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4">
                    <a href="<?php echo $google_auth_url; ?>" class="w-full flex items-center justify-center gap-2 bg-background-dark border border-accent-dark hover:border-slate-500 hover:bg-slate-800/50 text-slate-200 px-4 py-3 rounded-xl font-bold text-sm transition-all active:scale-95">
                        <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-5 h-5">
                        Google
                    </a>

                    <button type="button" class="w-full flex items-center justify-center gap-2 bg-[#1877F2] hover:bg-[#1864ce] text-white px-4 py-3 rounded-xl font-bold text-sm transition-all shadow-sm active:scale-95">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                        </svg>
                        Facebook
                    </button>
                </div>

                <div class="mt-8 text-center border-t border-slate-700/50 pt-6">
                    <p class="text-slate-400 text-sm">
                        Chưa có tài khoản? 
                        <a class="text-primary font-bold hover:underline ml-1" href="register.php">Đăng ký ngay</a>
                    </p>
                </div>
            </div>
            
            <div class="mt-8 flex justify-center gap-6">
                <a class="text-slate-500 hover:text-slate-300 text-xs transition-colors" href="index.php">Quay về trang chủ</a>
            </div>
        </div>
    </div>
</body>
</html>