<?php
session_start();
require_once 'includes/db_connect.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Lấy lịch sử vé của user
$stmt = $pdo->prepare("
    SELECT o.*, m.title as movie_title, m.poster_url 
    FROM orders o 
    JOIN movies m ON o.movie_id = m.id 
    WHERE o.user_id = :user_id 
    ORDER BY o.created_at DESC
");
$stmt->execute(['user_id' => $user_id]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Vé của tôi - Gold Cinema</title>
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
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="bg-primary text-background-dark p-1.5 rounded-lg"><span class="material-symbols-outlined text-2xl font-bold">theater_comedy</span></div>
                <h1 class="text-2xl font-black tracking-tight text-primary uppercase">Gold Cinema</h1>
            </a>
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-slate-300 hover:text-primary transition-colors text-sm font-bold flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">home</span> Trang chủ</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-5xl mx-auto w-full px-6 py-12">
        <div class="mb-8 border-l-4 border-primary pl-4">
            <h2 class="text-3xl font-black uppercase tracking-tight">Vé của tôi</h2>
            <p class="text-slate-400 mt-1">Quản lý lịch sử đặt vé và các suất chiếu sắp tới của bạn.</p>
        </div>

        <div class="space-y-6">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): 
                    // 1. Tạo mã vé từ ID
                    $order_code = "CGV" . str_pad($order['id'], 8, "0", STR_PAD_LEFT);
                    
                    // 2. TẠO LINK KIỂM TRA VÉ ĐỘNG
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $domain = $_SERVER['HTTP_HOST'];
                    $verify_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']) . "/kiemtra_ve.php?code=" . $order_code;
                    $qr_data = urlencode($verify_url);
                ?>
                <div class="bg-surface-dark border border-border-dark rounded-2xl p-4 md:p-6 flex flex-col md:flex-row gap-6 hover:border-primary/50 transition-colors shadow-lg group relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 size-32 bg-primary/5 rounded-full blur-2xl pointer-events-none group-hover:bg-primary/10 transition-all"></div>
                    
                    <div class="w-full md:w-32 aspect-[2/3] md:aspect-auto md:h-full min-h-[160px] rounded-xl bg-cover bg-center shadow-md flex-shrink-0 border border-border-dark" style="background-image: url('<?php echo htmlspecialchars($order['poster_url']); ?>');"></div>
                    
                    <div class="flex-1 flex flex-col lg:flex-row justify-between gap-4">
                        
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-bold text-slate-100 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($order['movie_title']); ?></h3>
                                    <?php if ($order['status'] == 'completed'): ?>
                                        <span class="px-3 py-1 rounded-full bg-green-500/10 text-green-500 text-[10px] font-black uppercase tracking-widest border border-green-500/20 shrink-0">Thành công</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-500 text-[10px] font-black uppercase tracking-widest border border-red-500/20 shrink-0">Đã hủy</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-1">Mã vé</p>
                                        <p class="text-sm font-mono text-slate-200">#<?php echo $order_code; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-1">Thời gian</p>
                                        <p class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($order['show_time']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-1">Ghế ngồi</p>
                                        <p class="text-sm font-black text-primary"><?php echo htmlspecialchars($order['seat_numbers']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-1">Tổng tiền</p>
                                        <p class="text-sm font-bold text-slate-200"><?php echo number_format($order['total_price'], 0, ',', '.'); ?>đ</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-border-dark flex justify-between items-center text-xs text-slate-500">
                                <span>Ngày đặt: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                                <span>Thanh toán: <?php echo htmlspecialchars($order['payment_method']); ?></span>
                            </div>
                        </div>

                        <div class="flex flex-col items-center justify-center border-t lg:border-t-0 lg:border-l border-border-dark pt-4 lg:pt-0 lg:pl-6 min-w-[130px]">
                            <?php if ($order['status'] == 'completed'): ?>
                                <div class="bg-white p-2 rounded-lg shadow-sm mb-2 group-hover:scale-105 transition-transform">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $qr_data; ?>" alt="QR Code" class="w-24 h-24 md:w-28 md:h-28 object-contain">
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium text-center leading-tight">Mã quét vào rạp</p>
                            <?php else: ?>
                                <div class="size-20 md:size-24 rounded-lg bg-background-dark border border-border-dark flex items-center justify-center mb-2">
                                    <span class="material-symbols-outlined text-slate-600 text-3xl">qr_code_scanner</span>
                                </div>
                                <p class="text-[10px] text-slate-500 font-medium text-center leading-tight">Vé không hợp lệ</p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-surface-dark border border-border-dark rounded-2xl p-16 text-center flex flex-col items-center">
                    <span class="material-symbols-outlined text-6xl text-slate-600 mb-4">local_activity</span>
                    <h3 class="text-xl font-bold text-slate-200 mb-2">Bạn chưa có vé nào</h3>
                    <p class="text-slate-400 mb-6">Hãy khám phá các bộ phim đang chiếu và đặt cho mình một vị trí đẹp nhé!</p>
                    <a href="index.php" class="bg-primary text-background-dark px-6 py-3 rounded-xl font-bold shadow-lg">Đặt vé ngay</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>