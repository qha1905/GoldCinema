<?php
session_start();
require_once 'includes/db_connect.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Nhận ID khách hàng từ URL
$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    header("Location: admin_users.php");
    exit;
}

// 1. Lấy thông tin khách hàng
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'member'");
$stmt_user->execute(['id' => $user_id]);
$customer = $stmt_user->fetch();

if (!$customer) {
    header("Location: admin_users.php"); // Nếu không tìm thấy khách, đá về trang danh sách
    exit;
}

// 2. Lấy lịch sử đặt vé của khách hàng này
$stmt_orders = $pdo->prepare("
    SELECT o.id, o.show_time, o.seat_numbers, o.total_price, o.payment_method, o.status, o.created_at, 
           m.title as movie_title, m.poster_url
    FROM orders o
    JOIN movies m ON o.movie_id = m.id
    WHERE o.user_id = :user_id
    ORDER BY o.created_at DESC
");
$stmt_orders->execute(['user_id' => $user_id]);
$orders = $stmt_orders->fetchAll();

// 3. Tính toán thống kê cho khách hàng này
$total_orders = count($orders);
$total_spent = 0;
$successful_orders = 0;

foreach ($orders as $order) {
    if ($order['status'] == 'completed') {
        $total_spent += $order['total_price'];
        $successful_orders++;
    }
}

// Tính hạng
$rank = 'Silver';
$rank_class = 'bg-slate-500 text-white';
if ($total_spent >= 2000000) { 
    $rank = 'Platinum'; 
    $rank_class = 'bg-slate-200 text-slate-900'; 
} elseif ($total_spent >= 500000) { 
    $rank = 'Gold'; 
    $rank_class = 'bg-primary text-background-dark'; 
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Lịch sử đặt vé - <?php echo htmlspecialchars($customer['fullname']); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; } .custom-scrollbar::-webkit-scrollbar { width: 6px; } .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4 flex-1">
                <a href="admin_users.php" class="flex items-center gap-2 text-slate-400 hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span class="font-bold text-sm">Quay lại danh sách</span>
                </a>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-slate-100 leading-none"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                        <p class="text-[10px] text-primary font-medium uppercase mt-1">Quản trị viên</p>
                    </div>
                    <div class="w-10 h-10 rounded-full border-2 border-primary/30 flex items-center justify-center bg-accent-dark text-primary font-bold">
                        <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            <div class="max-w-5xl mx-auto">
                
                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-6 mb-8 shadow-xl flex flex-col md:flex-row items-center md:items-start gap-6">
                    <div class="size-24 rounded-full bg-accent-dark border-2 border-primary/30 flex items-center justify-center text-primary text-4xl font-black shrink-0">
                        <?php echo mb_substr(trim($customer['fullname']), 0, 1, "UTF-8"); ?>
                    </div>
                    <div class="flex-1 text-center md:text-left space-y-2">
                        <div class="flex flex-col md:flex-row md:items-center gap-3">
                            <h2 class="text-2xl font-black text-slate-100 uppercase tracking-tight"><?php echo htmlspecialchars($customer['fullname']); ?></h2>
                            <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $rank_class; ?> w-fit mx-auto md:mx-0">
                                <?php echo $rank; ?>
                            </span>
                        </div>
                        <div class="flex flex-col md:flex-row gap-4 text-slate-400 text-sm">
                            <span class="flex items-center gap-1 justify-center md:justify-start"><span class="material-symbols-outlined text-sm">mail</span> <?php echo htmlspecialchars($customer['email']); ?></span>
                            <span class="flex items-center gap-1 justify-center md:justify-start"><span class="material-symbols-outlined text-sm">call</span> <?php echo htmlspecialchars($customer['phone']); ?></span>
                            <span class="flex items-center gap-1 justify-center md:justify-start"><span class="material-symbols-outlined text-sm">calendar_month</span> Tham gia: <?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="flex gap-4 md:border-l md:border-accent-dark md:pl-6 w-full md:w-auto">
                        <div class="flex-1 md:flex-none text-center md:text-right">
                            <p class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-1">Tổng chi tiêu</p>
                            <p class="text-2xl font-black text-primary"><?php echo number_format($total_spent, 0, ',', '.'); ?>đ</p>
                        </div>
                        <div class="flex-1 md:flex-none text-center md:text-right">
                            <p class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-1">Đơn thành công</p>
                            <p class="text-2xl font-black text-slate-100"><?php echo $successful_orders; ?> <span class="text-sm font-normal text-slate-500">/ <?php echo $total_orders; ?></span></p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-slate-100 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">receipt_long</span>
                        Lịch sử giao dịch chi tiết
                    </h3>
                </div>

                <div class="space-y-4">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                        <div class="bg-background-dark border border-accent-dark p-5 rounded-2xl flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5 hover:border-primary/40 transition-all shadow-lg group">
                            
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-24 rounded-lg bg-cover bg-center border border-accent-dark shadow-md shrink-0" style="background-image: url('<?php echo htmlspecialchars($order['poster_url']); ?>');"></div>
                                <div class="flex flex-col gap-1">
                                    <h4 class="text-lg text-slate-100 font-bold group-hover:text-primary transition-colors"><?php echo htmlspecialchars($order['movie_title']); ?></h4>
                                    <p class="text-sm text-slate-400 font-medium">
                                        <span class="text-slate-300">Ghế: <span class="text-primary font-bold"><?php echo htmlspecialchars($order['seat_numbers']); ?></span></span> 
                                        <span class="mx-2 opacity-30">|</span> 
                                        Thời gian: <?php echo htmlspecialchars($order['show_time']); ?>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">account_balance_wallet</span> 
                                        Thanh toán: <?php echo htmlspecialchars($order['payment_method']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex flex-row lg:flex-col justify-between lg:justify-center items-center lg:items-end w-full lg:w-auto border-t border-accent-dark lg:border-t-0 pt-4 lg:pt-0">
                                <div class="text-left lg:text-right">
                                    <p class="text-primary font-black text-xl"><?php echo number_format($order['total_price'], 0, ',', '.'); ?>đ</p>
                                    <p class="text-xs text-slate-500 font-mono mt-1">Mã đơn: #CGV<?php echo str_pad($order['id'], 8, "0", STR_PAD_LEFT); ?></p>
                                </div>
                                <div class="mt-0 lg:mt-3">
                                    <?php if ($order['status'] == 'completed'): ?>
                                        <span class="px-3 py-1 rounded-md bg-green-500/10 text-green-500 text-[10px] font-black uppercase tracking-widest border border-green-500/20">Thành công</span>
                                    <?php elseif ($order['status'] == 'pending'): ?>
                                        <span class="px-3 py-1 rounded-md bg-amber-500/10 text-amber-500 text-[10px] font-black uppercase tracking-widest border border-amber-500/20">Chờ thanh toán</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-md bg-red-500/10 text-red-500 text-[10px] font-black uppercase tracking-widest border border-red-500/20">Đã hủy</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-surface-dark border border-accent-dark rounded-2xl p-12 text-center flex flex-col items-center justify-center">
                            <div class="size-16 rounded-full bg-accent-dark flex items-center justify-center text-slate-500 mb-4">
                                <span class="material-symbols-outlined text-3xl">receipt_long</span>
                            </div>
                            <h4 class="text-lg font-bold text-slate-200 mb-1">Chưa có giao dịch</h4>
                            <p class="text-sm text-slate-400">Khách hàng này chưa thực hiện bất kỳ giao dịch đặt vé nào trên hệ thống.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </main>
</div>
</body>
</html>