<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. NHẬN THAM SỐ TÌM KIẾM, LỌC & SẮP XẾP
// ==========================================
$search_query = $_GET['search'] ?? '';
$filter_rank = $_GET['rank'] ?? '';

// LOGIC SẮP XẾP MỚI
$sort_by = $_GET['sort'] ?? 'created_at'; // Mặc định sắp xếp theo ngày tham gia
$sort_order = $_GET['order'] ?? 'DESC';   // Mặc định mới nhất lên đầu
$valid_sort_cols = ['created_at', 'total_spent'];
if (!in_array($sort_by, $valid_sort_cols)) $sort_by = 'created_at';
$sort_order = (strtoupper($sort_order) === 'ASC') ? 'ASC' : 'DESC';

$where_sql = "u.role = 'member'";
$params = [];

if (!empty($search_query)) {
    $where_sql .= " AND (u.fullname LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = "%" . $search_query . "%";
}

// ==========================================
// 2. TÍNH TOÁN THỐNG KÊ HẠNG
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
    if ($u['total_spent'] >= 10000000) $rank_counts['Platinum']++;
    elseif ($u['total_spent'] >= 2000000) $rank_counts['Gold']++;
    else $rank_counts['Silver']++;
}

$having_sql = "1=1";
if ($filter_rank == 'Platinum') {
    $having_sql = "total_spent >= 10000000";
} elseif ($filter_rank == 'Gold') {
    $having_sql = "total_spent >= 2000000 AND total_spent < 10000000";
} elseif ($filter_rank == 'Silver') {
    $having_sql = "total_spent < 2000000";
}

// ==========================================
// 3. THIẾT LẬP PHÂN TRANG (PAGINATION)
// ==========================================
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$sql_count = "SELECT COUNT(*) FROM (SELECT u.id, COALESCE(SUM(o.total_price), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'completed' WHERE $where_sql GROUP BY u.id HAVING $having_sql) as ct";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $limit;

// ==========================================
// 4. LẤY DANH SÁCH (CÓ SẮP XẾP VÀ PHÂN TRANG)
// ==========================================
$sql_list = "
    SELECT u.id, u.fullname, u.email, u.phone, u.created_at, 
           COUNT(o.id) as total_orders, 
           COALESCE(SUM(o.total_price), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'completed'
    WHERE $where_sql
    GROUP BY u.id
    HAVING $having_sql
    ORDER BY $sort_by $sort_order
    LIMIT $limit OFFSET $offset
";
$stmt_list = $pdo->prepare($sql_list);
$stmt_list->execute($params);
$customers = $stmt_list->fetchAll();

// Xử lý Query String cho các link (giữ lại search, rank, sort, order)
function getUrl($p, $s, $o, $r, $q) {
    $params = ['page' => $p, 'sort' => $s, 'order' => $o];
    if($r) $params['rank'] = $r;
    if($q) $params['search'] = $q;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/><meta content="width=device-width, initial-scale=1.0" name="viewport"/><title>Quản lý Khách hàng - CineAdmin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"primary":"#f2cc0d","background-dark":"#1a180b","surface-dark":"#2a2614","accent-dark":"#403a1e"}}}}</script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180b; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col">
<div class="flex h-screen overflow-hidden">
    <?php require_once 'includes/admin_sidebar.php'; ?>
    <main class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4 flex-1">
                <div class="lg:hidden text-primary cursor-pointer"><span class="material-symbols-outlined">menu</span></div>
            </div>
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
            
            <div class="flex flex-col mb-8">
                <h1 class="text-3xl font-black text-slate-100 uppercase tracking-tight">Quản lý Khách hàng</h1>
                <p class="text-slate-400 mt-1">Quản lý danh sách, phân hạng và lịch sử đặt vé của người dùng.</p>
            </div>

            <form method="GET" class="bg-surface-dark border border-accent-dark p-4 rounded-2xl mb-8 flex flex-col md:flex-row gap-4 shadow-lg items-center">
                <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
                <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
                <div class="flex-1 relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Tìm tên, email..." class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 pl-10 pr-4 text-slate-200 outline-none focus:border-primary transition-all">
                </div>
                <div class="flex gap-2 overflow-x-auto w-full md:w-auto">
                    <?php foreach(['' => 'Tất cả', 'Platinum' => 'Platinum', 'Gold' => 'Gold', 'Silver' => 'Silver'] as $r_val => $r_lbl): ?>
                        <button type="submit" name="rank" value="<?php echo $r_val; ?>" class="px-5 py-3 rounded-xl font-bold transition-all text-sm <?php echo $filter_rank == $r_val ? 'bg-primary text-background-dark shadow-lg' : 'bg-accent-dark/50 text-slate-400 hover:text-white'; ?>">
                            <?php echo $r_lbl; ?> (<?php echo $rank_counts[$r_val ?: 'All']; ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <div class="lg:col-span-3 flex flex-col gap-4">
                    <div class="bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[750px]">
                                <thead>
                                    <tr class="bg-accent-dark/30 border-b border-accent-dark text-[11px] font-black text-primary uppercase tracking-widest">
                                        <th class="px-6 py-5">
                                            <div class="flex items-center gap-2">
                                                Khách hàng
                                                <a href="<?php echo getUrl($page, 'created_at', $sort_order == 'DESC' ? 'ASC' : 'DESC', $filter_rank, $search_query); ?>" class="hover:text-white transition-colors" title="Sắp xếp theo thời gian tham gia">
                                                    <span class="material-symbols-outlined text-[18px]"><?php echo $sort_by == 'created_at' ? ($sort_order == 'DESC' ? 'arrow_downward' : 'arrow_upward') : 'swap_vert'; ?></span>
                                                </a>
                                            </div>
                                        </th>
                                        <th class="px-6 py-5">Liên hệ</th>
                                        <th class="px-6 py-5 text-center">Hạng</th>
                                        <th class="px-6 py-5">
                                            <div class="flex items-center gap-2">
                                                Tổng chi tiêu
                                                <a href="<?php echo getUrl($page, 'total_spent', $sort_order == 'DESC' ? 'ASC' : 'DESC', $filter_rank, $search_query); ?>" class="hover:text-white transition-colors" title="Sắp xếp theo mức chi tiêu">
                                                    <span class="material-symbols-outlined text-[18px]"><?php echo $sort_by == 'total_spent' ? ($sort_order == 'DESC' ? 'arrow_downward' : 'arrow_upward') : 'swap_vert'; ?></span>
                                                </a>
                                            </div>
                                        </th>
                                        <th class="px-6 py-5 text-center">Lịch sử</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-dark/50">
                                    <?php foreach ($customers as $cus): 
                                        $spent = $cus['total_spent'];
                                        if ($spent >= 10000000) { $rank = 'Platinum'; $cls = 'bg-slate-200 text-slate-900'; }
                                        elseif ($spent >= 2000000) { $rank = 'Gold'; $cls = 'bg-primary text-background-dark'; }
                                        else { $rank = 'Silver'; $cls = 'bg-slate-600 text-white'; }
                                    ?>
                                    <tr class="hover:bg-accent-dark/20 transition-colors group">
                                        <td class="px-6 py-5">
                                            <div class="flex items-center gap-3">
                                                <div class="size-10 rounded-full bg-accent-dark flex items-center justify-center text-primary font-bold border border-primary/20"><?php echo mb_substr(trim($cus['fullname']), 0, 1, "UTF-8"); ?></div>
                                                <div>
                                                    <p class="text-sm font-bold text-slate-100 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($cus['fullname']); ?></p>
                                                    <p class="text-[11px] text-slate-500 italic">Tham gia: <?php echo date('d/m/Y', strtotime($cus['created_at'])); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5"><p class="text-sm text-slate-300"><?php echo htmlspecialchars($cus['email']); ?></p><p class="text-xs text-slate-500"><?php echo htmlspecialchars($cus['phone']); ?></p></td>
                                        <td class="px-6 py-5 text-center"><span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $cls; ?>"><?php echo $rank; ?></span></td>
                                        <td class="px-6 py-5"><p class="text-sm font-bold text-primary"><?php echo number_format($spent, 0, ',', '.'); ?>đ</p><p class="text-[11px] text-slate-500"><?php echo $cus['total_orders']; ?> hóa đơn</p></td>
                                        <td class="px-6 py-5 text-center"><a href="admin_user_history.php?id=<?php echo $cus['id']; ?>" class="inline-block p-2 hover:bg-primary/20 rounded-lg text-slate-400 hover:text-primary transition-all"><span class="material-symbols-outlined text-xl">history</span></a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <div class="px-6 py-4 border-t border-accent-dark bg-background-dark/30 flex flex-col md:flex-row items-center justify-between gap-4">
                            <p class="text-sm text-slate-500 font-medium">Trang <span class="text-slate-100"><?php echo $page; ?></span> / <?php echo $total_pages; ?> (<?php echo $total_records; ?> khách)</p>
                            <div class="flex items-center gap-1">
                                <a href="<?php echo getUrl(1, $sort_by, $sort_order, $filter_rank, $search_query); ?>" class="px-2 py-1.5 rounded-lg bg-surface-dark border border-accent-dark text-slate-400 hover:text-primary transition-all <?php if($page <= 1) echo 'pointer-events-none opacity-30'; ?>" title="Trang đầu">
                                    <span class="material-symbols-outlined text-sm">first_page</span>
                                </a>
                                <a href="<?php echo getUrl(max(1, $page - 1), $sort_by, $sort_order, $filter_rank, $search_query); ?>" class="px-2 py-1.5 rounded-lg bg-surface-dark border border-accent-dark text-slate-400 hover:text-primary transition-all <?php if($page <= 1) echo 'pointer-events-none opacity-30'; ?>">
                                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                                </a>
                                <?php
                                $start = max(1, $page - 1); $end = min($total_pages, $page + 1);
                                for ($i = $start; $i <= $end; $i++) {
                                    $active = ($i == $page) ? 'bg-primary text-background-dark shadow-md' : 'bg-surface-dark text-slate-400 hover:text-primary';
                                    echo '<a href="'.getUrl($i, $sort_by, $sort_order, $filter_rank, $search_query).'" class="px-3 py-1.5 text-sm font-bold rounded-lg border border-accent-dark transition-all '.$active.'">'.$i.'</a>';
                                }
                                ?>
                                <a href="<?php echo getUrl(min($total_pages, $page + 1), $sort_by, $sort_order, $filter_rank, $search_query); ?>" class="px-2 py-1.5 rounded-lg bg-surface-dark border border-accent-dark text-slate-400 hover:text-primary transition-all <?php if($page >= $total_pages) echo 'pointer-events-none opacity-30'; ?>">
                                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                                </a>
                                <a href="<?php echo getUrl($total_pages, $sort_by, $sort_order, $filter_rank, $search_query); ?>" class="px-2 py-1.5 rounded-lg bg-surface-dark border border-accent-dark text-slate-400 hover:text-primary transition-all <?php if($page >= $total_pages) echo 'pointer-events-none opacity-30'; ?>" title="Trang cuối">
                                    <span class="material-symbols-outlined text-sm">last_page</span>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-surface-dark border border-accent-dark rounded-2xl p-6 h-fit sticky top-4 shadow-2xl">
                        <h3 class="text-lg font-bold text-slate-100 mb-6 uppercase tracking-wider flex items-center gap-2"><span class="material-symbols-outlined text-primary">sell</span> Tóm tắt hạng</h3>
                        <div class="space-y-6">
                            <?php foreach(['Platinum' => ['cls' => 'text-slate-200', 'lbl' => 'Platinum'], 'Gold' => ['cls' => 'text-primary', 'lbl' => 'Gold'], 'Silver' => ['cls' => 'text-slate-500', 'lbl' => 'Silver']] as $rk => $cfg): ?>
                                <div class="flex items-center justify-between border-b border-accent-dark pb-4">
                                    <span class="<?php echo $cfg['cls']; ?> text-sm font-bold"><?php echo $cfg['lbl']; ?></span>
                                    <span class="text-slate-300 font-bold"><?php echo $rank_counts[$rk]; ?> khách</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>