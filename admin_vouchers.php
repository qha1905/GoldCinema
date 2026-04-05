<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = '';

// Xử lý Thêm mã giảm giá
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_voucher'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount_amount = (int)$_POST['discount_amount'];
    $min_order_value = (int)$_POST['min_order_value'];
    $expiry_date = $_POST['expiry_date'];
    $usage_limit = (int)$_POST['usage_limit'];

    try {
        $stmt = $pdo->prepare("INSERT INTO vouchers (code, discount_amount, min_order_value, expiry_date, usage_limit, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$code, $discount_amount, $min_order_value, $expiry_date, $usage_limit]);
        $msg = "<div class='bg-green-500/10 text-green-500 p-4 rounded-xl mb-6 font-bold border border-green-500/20'>Thêm mã giảm giá thành công!</div>";
    } catch (PDOException $e) {
        $msg = "<div class='bg-red-500/10 text-red-500 p-4 rounded-xl mb-6 font-bold border border-red-500/20'>Lỗi: Mã này có thể đã tồn tại.</div>";
    }
}

// Xử lý Xóa mã giảm giá
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM vouchers WHERE id = ?")->execute([$_GET['id']]);
    header("Location: admin_vouchers.php");
    exit;
}

// Lấy danh sách Voucher
$vouchers = $pdo->query("SELECT * FROM vouchers ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Quản lý Mã giảm giá - CineAdmin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"primary":"#f2cc0d","background-dark":"#1a180b","surface-dark":"#2a2614","accent-dark":"#403a1e"}}}}</script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180b; } .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }</style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col">
<div class="flex h-screen overflow-hidden">
    <?php require_once 'includes/admin_sidebar.php'; ?>
    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4 flex-1"></div>
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-slate-100 leading-none"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-[10px] text-primary font-medium uppercase mt-1">Quản trị viên</p>
                </div>
                <div class="w-10 h-10 rounded-full border-2 border-primary/30 flex items-center justify-center bg-accent-dark text-primary font-bold">
                    <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            <?php echo $msg; ?>
            <div class="flex flex-col mb-8">
                <h1 class="text-3xl font-black text-slate-100 uppercase tracking-tight">Quản lý mã giảm giá</h1>
                <p class="text-slate-400 mt-1">Tạo và quản lý các chiến dịch khuyến mãi cho khách hàng.</p>
            </div>

            <form method="POST" class="bg-surface-dark border border-accent-dark rounded-2xl p-6 mb-8 shadow-xl">
                <h3 class="text-lg font-bold text-primary mb-4 border-b border-accent-dark pb-2">Thêm Voucher mới</h3>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Mã Code (VD: TET2026)</label>
                        <input type="text" name="code" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary uppercase">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Số tiền giảm (VNĐ)</label>
                        <input type="number" name="discount_amount" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Đơn tối thiểu (VNĐ)</label>
                        <input type="number" name="min_order_value" value="0" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Hạn sử dụng</label>
                        <input type="date" name="expiry_date" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary" style="color-scheme: dark;">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Số lượt dùng</label>
                        <div class="flex gap-2">
                            <input type="number" name="usage_limit" value="100" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                            <button type="submit" name="add_voucher" class="bg-primary text-background-dark font-bold px-4 py-2 rounded-xl hover:bg-primary/90 transition-all"><span class="material-symbols-outlined">add</span></button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-accent-dark/30 border-b border-accent-dark text-[11px] font-black text-primary uppercase tracking-widest">
                                <th class="px-6 py-4">Mã Code</th>
                                <th class="px-6 py-4">Mức giảm</th>
                                <th class="px-6 py-4">Đơn tối thiểu</th>
                                <th class="px-6 py-4 text-center">Đã dùng</th>
                                <th class="px-6 py-4 text-center">Hạn sử dụng</th>
                                <th class="px-6 py-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-dark/50 text-sm">
                            <?php foreach ($vouchers as $v): 
                                $is_expired = (strtotime($v['expiry_date']) < strtotime(date('Y-m-d')));
                                $is_out = ($v['used_count'] >= $v['usage_limit']);
                            ?>
                            <tr class="hover:bg-accent-dark/20 transition-colors group <?php if($is_expired || $is_out) echo 'opacity-50'; ?>">
                                <td class="px-6 py-4 font-mono font-bold text-slate-200 tracking-wider"><?php echo htmlspecialchars($v['code']); ?></td>
                                <td class="px-6 py-4 font-bold text-green-500">-<?php echo number_format($v['discount_amount'], 0, ',', '.'); ?>đ</td>
                                <td class="px-6 py-4 text-slate-400"><?php echo number_format($v['min_order_value'], 0, ',', '.'); ?>đ</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="<?php echo $is_out ? 'text-red-500 font-bold' : 'text-primary'; ?>"><?php echo $v['used_count']; ?></span> / <span class="text-slate-500"><?php echo $v['usage_limit']; ?></span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="<?php echo $is_expired ? 'bg-red-500/10 text-red-500' : 'bg-background-dark text-slate-300'; ?> px-3 py-1 rounded-md border border-accent-dark">
                                        <?php echo date('d/m/Y', strtotime($v['expiry_date'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="?action=delete&id=<?php echo $v['id']; ?>" onclick="return confirm('Xóa mã giảm giá này?');" class="inline-block p-2 rounded-lg text-slate-500 hover:bg-red-500/10 hover:text-red-500 transition-all"><span class="material-symbols-outlined text-lg">delete</span></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>