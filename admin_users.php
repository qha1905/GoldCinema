<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. NHẬN THAM SỐ TÌM KIẾM & LỌC
// ==========================================
$search_query = $_GET['search'] ?? '';
$filter_rank = $_GET['rank'] ?? '';

$where_sql = "u.role = 'member'";
$params = [];

// Xử lý Tìm kiếm (Theo tên, email hoặc SĐT)
if (!empty($search_query)) {
    $where_sql .= " AND (u.fullname LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = "%" . $search_query . "%";
}

// ==========================================
// 2. TÍNH TOÁN THỐNG KÊ (Không bị ảnh hưởng bởi bộ lọc Hạng)
// ==========================================
$sql_summary = "
    SELECT u.id, COALESCE(SUM(o.total_price), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'completed'
    WHERE $where_sql
    GROUP BY u.id
";
$stmt_summary = $pdo->prepare($sql_summary);
$stmt_summary->execute($params);
$all_filtered_users = $stmt_summary->fetchAll();

$rank_counts = ['Platinum' => 0, 'Gold' => 0, 'Silver' => 0, 'All' => count($all_filtered_users)];
foreach ($all_filtered_users as $u) {
    if ($u['total_spent'] >= 2000000) $rank_counts['Platinum']++;
    elseif ($u['total_spent'] >= 500000) $rank_counts['Gold']++;
    else $rank_counts['Silver']++;
}

// ==========================================
// 3. XỬ LÝ LỌC THEO HẠNG (Dùng lệnh HAVING)
// ==========================================
$having_sql = "1=1";
if ($filter_rank == 'Platinum') {
    $having_sql = "total_spent >= 2000000";
} elseif ($filter_rank == 'Gold') {
    $having_sql = "total_spent >= 500000 AND total_spent < 2000000";
} elseif ($filter_rank == 'Silver') {
    $having_sql = "total_spent < 500000";
}

// ==========================================
// 4. LẤY DANH SÁCH KHÁCH HÀNG CHÍNH THỨC
// ==========================================
// Đã chuyển total_orders về đếm số lượt hóa đơn (COUNT(o.id))
$sql_list = "
    SELECT u.id, u.fullname, u.email, u.phone, u.created_at, 
           COUNT(o.id) as total_orders, 
           COALESCE(SUM(o.total_price), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'completed'
    WHERE $where_sql
    GROUP BY u.id
    HAVING $having_sql
    ORDER BY u.created_at DESC
";
$stmt_list = $pdo->prepare($sql_list);
$stmt_list->execute($params);
$customers = $stmt_list->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Quản lý Khách hàng - Admin Cinema</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
        input:-webkit-autofill { -webkit-box-shadow: 0 0 0 30px #2a2614 inset !important; -webkit-text-fill-color: #e2e8f0 !important; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4 flex-1">
                <div class="lg:hidden text-primary cursor-pointer"><span class="material-symbols-outlined">menu</span></div>
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
            <div class="flex flex-col mb-8">
                <h2 class="text-3xl font-black text-slate-100 tracking-tight uppercase">Quản lý Khách hàng</h2>
                <p class="text-slate-400 mt-1">Quản lý danh sách, phân hạng và lịch sử đặt vé của người dùng.</p>
            </div>

            <form method="GET" action="admin_users.php" class="bg-surface-dark border border-accent-dark p-4 rounded-2xl mb-8 flex flex-col md:flex-row gap-4 shadow-lg items-center">
                <div class="flex-1 relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off" placeholder="Tìm tên, email hoặc số điện thoại..." class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 pl-10 pr-4 text-slate-200 focus:bg-accent-dark/30 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                </div>
                
                <div class="flex gap-2 overflow-x-auto pb-2 md:pb-0 custom-scrollbar w-full md:w-auto">
                    <button type="submit" name="rank" value="" class="px-5 py-3 rounded-xl font-bold transition-all whitespace-nowrap text-sm <?php echo $filter_rank == '' ? 'bg-primary text-background-dark shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]' : 'bg-accent-dark/50 text-slate-300 hover:bg-accent-dark hover:text-white'; ?>">
                        Tất cả (<?php echo $rank_counts['All']; ?>)
                    </button>
                    <button type="submit" name="rank" value="Platinum" class="px-5 py-3 rounded-xl font-bold transition-all whitespace-nowrap text-sm <?php echo $filter_rank == 'Platinum' ? 'bg-slate-200 text-slate-900 shadow-[0_4px_14px_0_rgba(226,232,240,0.39)]' : 'bg-accent-dark/50 text-slate-300 hover:bg-accent-dark hover:text-white'; ?>">
                        Platinum (<?php echo $rank_counts['Platinum']; ?>)
                    </button>
                    <button type="submit" name="rank" value="Gold" class="px-5 py-3 rounded-xl font-bold transition-all whitespace-nowrap text-sm <?php echo $filter_rank == 'Gold' ? 'bg-primary text-background-dark shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]' : 'bg-accent-dark/50 text-slate-300 hover:bg-accent-dark hover:text-white'; ?>">
                        Gold (<?php echo $rank_counts['Gold']; ?>)
                    </button>
                    <button type="submit" name="rank" value="Silver" class="px-5 py-3 rounded-xl font-bold transition-all whitespace-nowrap text-sm <?php echo $filter_rank == 'Silver' ? 'bg-slate-500 text-white shadow-[0_4px_14px_0_rgba(100,116,139,0.39)]' : 'bg-accent-dark/50 text-slate-300 hover:bg-accent-dark hover:text-white'; ?>">
                        Silver (<?php echo $rank_counts['Silver']; ?>)
                    </button>
                </div>
            </form>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <div class="lg:col-span-3 bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="bg-accent-dark/30 border-b border-accent-dark text-xs font-bold text-primary uppercase tracking-widest">
                                    <th class="px-6 py-4">Khách hàng</th>
                                    <th class="px-6 py-4">Liên hệ</th>
                                    <th class="px-6 py-4 text-center">Hạng</th>
                                    <th class="px-6 py-4">Tổng chi tiêu</th>
                                    <th class="px-6 py-4 text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-dark/50">
                                <?php foreach ($customers as $cus): 
                                    $spent = $cus['total_spent'];
                                    if ($spent >= 5000000) { $rank = 'Platinum'; $colorClass = 'bg-slate-200 text-slate-900'; }
                                    elseif ($spent >= 1000000) { $rank = 'Gold'; $colorClass = 'bg-primary text-background-dark'; }
                                    else { $rank = 'Silver'; $colorClass = 'bg-slate-600 text-white'; }
                                ?>
                                <tr class="hover:bg-accent-dark/20 transition-colors group">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-full bg-accent-dark flex items-center justify-center text-primary font-bold border border-primary/20">
                                                <?php echo mb_substr(trim($cus['fullname']), 0, 1, "UTF-8"); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-100 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($cus['fullname']); ?></p>
                                                <p class="text-xs text-slate-500">Tham gia: <?php echo date('d/m/Y', strtotime($cus['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <p class="text-sm text-slate-300"><?php echo htmlspecialchars($cus['email']); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars($cus['phone']); ?></p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $colorClass; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <p class="text-sm font-bold text-primary"><?php echo number_format($spent, 0, ',', '.'); ?>đ</p>
                                        <p class="text-xs text-slate-500"><?php echo $cus['total_orders']; ?> hóa đơn</p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <a href="admin_user_history.php?id=<?php echo $cus['id']; ?>" class="inline-block p-2 hover:bg-primary/20 rounded-lg text-slate-400 hover:text-primary transition-all" title="Xem lịch sử đặt vé">
                                            <span class="material-symbols-outlined text-xl">history</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if(count($customers) == 0): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                        <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span><br>
                                        Không tìm thấy khách hàng phù hợp.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-surface-dark border border-accent-dark rounded-2xl p-6 h-fit sticky top-0 shadow-2xl">
                        <h3 class="text-lg font-bold text-slate-100 mb-6 uppercase tracking-wider flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">sell</span> Tóm tắt hạng
                        </h3>
                        <div class="space-y-6">
                            <div class="flex items-center justify-between border-b border-accent-dark pb-4">
                                <span class="text-slate-400 text-sm">Platinum</span>
                                <span class="text-slate-100 font-bold"><?php echo $rank_counts['Platinum']; ?> khách</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-accent-dark pb-4">
                                <span class="text-primary text-sm font-bold">Gold</span>
                                <span class="text-primary font-bold"><?php echo $rank_counts['Gold']; ?> khách</span>
                            </div>
                            <div class="flex items-center justify-between border-b border-accent-dark pb-4">
                                <span class="text-slate-500 text-sm">Silver</span>
                                <span class="text-slate-300 font-bold"><?php echo $rank_counts['Silver']; ?> khách</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>