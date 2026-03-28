<?php
require_once 'includes/db_connect.php';

$code = $_GET['code'] ?? '';
$isValid = false;
$order = null;

if (!empty($code)) {
    // Tách lấy ID thật từ chuỗi CGV00000027
    $order_id = (int)str_replace('CGV', '', $code);

    if ($order_id > 0) {
        $stmt = $pdo->prepare("
            SELECT o.*, m.title, m.poster_url, u.fullname, u.phone
            FROM orders o
            JOIN movies m ON o.movie_id = m.id
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        // Kiểm tra xem đơn hàng có tồn tại và đã thanh toán chưa
        if ($order && $order['status'] === 'completed') {
            $isValid = true;
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Hệ Thống Soát Vé - Gold Cinema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180a; }</style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col justify-center items-center p-4">

    <div class="w-full max-w-md bg-[#2a2614] border border-[#494222] rounded-3xl p-6 shadow-2xl relative overflow-hidden">
        
        <div class="flex justify-center mb-6">
            <div class="bg-[#f2cc0d] text-[#1a180a] p-2 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-3xl font-bold">theater_comedy</span>
            </div>
        </div>

        <?php if ($isValid && $order): ?>
            <div class="absolute top-0 inset-x-0 h-2 bg-green-500"></div>
            
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center size-16 bg-green-500/10 text-green-500 rounded-full mb-3 border-2 border-green-500/20">
                    <span class="material-symbols-outlined text-4xl">check_circle</span>
                </div>
                <h2 class="text-2xl font-black uppercase tracking-tight text-green-500">Vé Hợp Lệ</h2>
                <p class="text-sm text-slate-400 mt-1 font-mono">#<?php echo htmlspecialchars($code); ?></p>
            </div>

            <div class="bg-[#1a180a] rounded-2xl p-4 border border-[#494222] mb-6 flex gap-4">
                <img src="<?php echo htmlspecialchars($order['poster_url']); ?>" alt="Poster" class="w-20 rounded-lg object-cover shadow-md border border-[#494222]">
                <div class="flex flex-col justify-center">
                    <h3 class="font-bold text-[#f2cc0d] text-lg leading-tight uppercase"><?php echo htmlspecialchars($order['title']); ?></h3>
                    <p class="text-xs text-slate-400 mt-1 font-medium">Giờ chiếu: <span class="text-slate-200"><?php echo htmlspecialchars($order['show_time']); ?></span></p>
                    <p class="text-xs text-slate-400 mt-0.5 font-medium">Phòng: <span class="text-slate-200 text-primary font-bold uppercase"><?php echo !empty($order['room_name']) ? htmlspecialchars($order['room_name']) : 'Phòng Tiêu Chuẩn'; ?></span></p> </div>
            </div>

            <div class="grid grid-cols-2 gap-4 bg-[#1a180a] rounded-2xl p-5 border border-[#494222]">
                <div>
                    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500">Khách Hàng</p>
                    <p class="text-sm font-bold text-slate-200 mt-0.5"><?php echo htmlspecialchars($order['fullname']); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500">SĐT</p>
                    <p class="text-sm font-bold text-slate-200 mt-0.5"><?php echo htmlspecialchars($order['phone']); ?></p>
                </div>
                <div class="col-span-2 pt-3 border-t border-[#494222] border-dashed text-center">
                    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500 mb-1">Ghế Ngồi</p>
                    <p class="text-2xl font-black text-[#f2cc0d]"><?php echo htmlspecialchars($order['seat_numbers']); ?></p>
                </div>
            </div>

        <?php elseif ($order && $order['status'] !== 'completed'): ?>
            <div class="absolute top-0 inset-x-0 h-2 bg-amber-500"></div>
            
            <div class="text-center py-8">
                <div class="inline-flex items-center justify-center size-20 bg-amber-500/10 text-amber-500 rounded-full mb-4 border-2 border-amber-500/20">
                    <span class="material-symbols-outlined text-5xl">warning</span>
                </div>
                <h2 class="text-2xl font-black uppercase tracking-tight text-amber-500">Vé Chưa Thanh Toán</h2>
                <p class="text-slate-400 mt-2 text-sm px-4">Vé này đã bị hủy hoặc chưa hoàn tất thanh toán. Vui lòng kiểm tra lại!</p>
                <p class="text-sm text-slate-500 mt-4 font-mono border border-[#494222] inline-block px-4 py-1 rounded-lg">#<?php echo htmlspecialchars($code); ?></p>
            </div>

        <?php else: ?>
            <div class="absolute top-0 inset-x-0 h-2 bg-red-500"></div>
            
            <div class="text-center py-8">
                <div class="inline-flex items-center justify-center size-20 bg-red-500/10 text-red-500 rounded-full mb-4 border-2 border-red-500/20">
                    <span class="material-symbols-outlined text-5xl">gpp_bad</span>
                </div>
                <h2 class="text-2xl font-black uppercase tracking-tight text-red-500">Vé Không Tồn Tại</h2>
                <p class="text-slate-400 mt-2 text-sm px-4">Mã vé giả mạo hoặc không có trong hệ thống dữ liệu của rạp chiếu.</p>
            </div>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="admin_dashboard.php" class="text-xs font-bold text-slate-500 hover:text-[#f2cc0d] transition-colors inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">admin_panel_settings</span> Quay lại hệ thống
            </a>
        </div>
    </div>

</body>
</html>