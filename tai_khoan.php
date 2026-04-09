<?php
session_start();
require_once 'includes/db_connect.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$message = '';

// Xử lý cập nhật thông tin
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    
    if(!empty($fullname)) {
        $stmt = $pdo->prepare("UPDATE users SET fullname = ?, phone = ? WHERE id = ?");
        $stmt->execute([$fullname, $phone, $user_id]);
        $_SESSION['user_name'] = $fullname; // Cập nhật lại session
        $message = "Cập nhật thông tin thành công!";
    }
}

// Lấy thông tin user
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// Tính tổng chi tiêu để xếp hạng
$stmt_spent = $pdo->prepare("SELECT SUM(total_price) as total FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt_spent->execute([$user_id]);
$total_spent = $stmt_spent->fetchColumn() ?: 0;

$rank = 'Silver';
$rank_class = 'bg-slate-500 text-white shadow-[0_4px_14px_0_rgba(100,116,139,0.39)]';
$next_rank_spent = 2000000;
$progress = ($total_spent / 500000) * 100;

if ($total_spent >= 10000000) { 
    $rank = 'Platinum'; 
    $rank_class = 'bg-slate-200 text-slate-900 shadow-[0_4px_14px_0_rgba(226,232,240,0.39)]'; 
    $progress = 100;
} elseif ($total_spent >= 2000000) { 
    $rank = 'Gold'; 
    $rank_class = 'bg-primary text-background-dark shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]'; 
    $next_rank_spent = 10000000;
    $progress = ($total_spent / 10000000) * 100;
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Tài khoản của tôi - Gold Cinema</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180a", "surface-dark": "#2a2614", "border-dark": "#494222", } } } }
    </script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180a; }</style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col">

    <header class="border-b border-border-dark bg-background-dark/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3 hover:scale-105 transition-transform">
                <img src="images/my_logo.png" alt="Logo Rạp Phim Của Tôi" class="h-12 w-12 object-cover rounded-full shadow-md border border-primary/30">
                <h1 class="text-2xl font-black tracking-tighter uppercase italic">H CINEMA</h1>
            </a>
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-slate-300 hover:text-primary transition-colors text-sm font-bold flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">home</span> Trang chủ</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-5xl mx-auto w-full px-6 py-12">
        <div class="mb-8 border-l-4 border-primary pl-4">
            <h2 class="text-3xl font-black uppercase tracking-tight">Hồ sơ tài khoản</h2>
            <p class="text-slate-400 mt-1">Quản lý thông tin cá nhân và điểm tích lũy thành viên.</p>
        </div>

        <?php if(!empty($message)): ?>
            <div class="bg-green-500/10 border border-green-500/30 text-green-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2 font-medium">
                <span class="material-symbols-outlined">check_circle</span> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <div class="md:col-span-1">
                <div class="bg-gradient-to-br from-surface-dark to-background-dark border border-border-dark rounded-2xl p-6 shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 opacity-5 text-white">
                        <span class="material-symbols-outlined text-[150px]">verified</span>
                    </div>
                    
                    <div class="flex items-center gap-4 mb-6 relative z-10">
                        <div class="size-16 rounded-full bg-border-dark flex items-center justify-center text-primary text-2xl font-black border border-primary/20">
                            <?php echo mb_substr(trim($user['fullname']), 0, 1, "UTF-8"); ?>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Thành viên</p>
                            <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider <?php echo $rank_class; ?>">
                                <?php echo $rank; ?>
                            </span>
                        </div>
                    </div>

                    <div class="relative z-10">
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">Tổng chi tiêu</p>
                        <p class="text-3xl font-black text-primary mb-4"><?php echo number_format($total_spent, 0, ',', '.'); ?>đ</p>
                        
                        <?php if ($rank != 'Platinum'): ?>
                            <div class="w-full bg-border-dark rounded-full h-2 mb-2">
                                <div class="bg-primary h-2 rounded-full" style="width: <?php echo min(100, $progress); ?>%"></div>
                            </div>
                            <p class="text-xs text-slate-500">
                                Cần chi tiêu thêm <span class="text-slate-300 font-bold"><?php echo number_format($next_rank_spent - $total_spent, 0, ',', '.'); ?>đ</span> để thăng hạng <?php echo $rank == 'Silver' ? 'Gold' : 'Platinum'; ?>
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-primary font-bold">Bạn đã đạt hạng thành viên cao nhất!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <form method="POST" action="tai_khoan.php" class="bg-surface-dark border border-border-dark rounded-2xl p-6 md:p-8 shadow-xl">
                    <h3 class="text-xl font-bold text-slate-100 mb-6 border-b border-border-dark pb-4">Thông tin cá nhân</h3>
                    
                    <div class="space-y-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-300">Họ và tên</label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required class="w-full bg-background-dark border border-border-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Email (Không thể đổi)</label>
                                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full bg-background-dark/50 border border-border-dark rounded-xl py-3 px-4 text-slate-500 cursor-not-allowed outline-none">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Số điện thoại</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="w-full bg-background-dark border border-border-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-border-dark flex justify-end">
                        <button type="submit" class="bg-primary text-background-dark font-bold px-8 py-3 rounded-xl hover:brightness-110 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] active:scale-95 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span> Cập nhật thông tin
                        </button>
                    </div>
                </form>
            </div>
            
        </div>
    </main>
</body>
</html>