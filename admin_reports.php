<?php
session_start();
require_once 'includes/db_connect.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. NHẬN THAM SỐ LỌC (Lọc theo Tháng & Rạp)
// ==========================================
$filter_month = $_GET['month'] ?? ''; // Định dạng: YYYY-MM
$filter_cinema = $_GET['cinema_id'] ?? '';

// Xử lý làm sạch biến để chống SQL Injection
$month_safe = preg_replace('/[^0-9-]/', '', $filter_month);
$cinema_safe = (int)$filter_cinema;

// Lấy danh sách rạp đổ vào Dropdown
$all_cinemas = $pdo->query("SELECT id, name FROM cinemas ORDER BY name ASC")->fetchAll();

// Lấy danh sách rạp đổ vào Dropdown
$all_cinemas = $pdo->query("SELECT id, name FROM cinemas ORDER BY name ASC")->fetchAll();

// TẠO DANH SÁCH 24 THÁNG GẦN NHẤT ĐỂ HIỂN THỊ DROPDOWN
$months_list = [];
for ($i = 0; $i < 12; $i++) {
    $timestamp = strtotime("first day of -$i month");
    $val = date('Y-m', $timestamp);
    $lbl = "Tháng: " . date('m/Y', $timestamp);
    $months_list[$val] = $lbl;
}

// ==========================================
// 2. CHUẨN BỊ ĐIỀU KIỆN TRUY VẤN
// ==========================================
// Điều kiện chung cho bảng orders (o)
$o_cond = "o.status = 'completed'";
if ($month_safe) {
    $o_cond .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month_safe'";
}
if ($cinema_safe) {
    // Nếu lọc theo rạp, chỉ lấy các đơn hàng của phim có chiếu tại rạp đó
    $o_cond .= " AND o.movie_id IN (SELECT movie_id FROM showtimes WHERE cinema_id = $cinema_safe)";
}

// ==========================================
// 3. TỔNG QUAN DOANH THU & VÉ (Tính theo số ghế)
// ==========================================
$sql_totals = "
    SELECT 
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(SUM(IF(seat_numbers IS NULL OR seat_numbers = '', 0, LENGTH(seat_numbers) - LENGTH(REPLACE(seat_numbers, ',', '')) + 1)), 0) as total_tickets
    FROM orders o 
    WHERE $o_cond
";
$totals = $pdo->query($sql_totals)->fetch();
$total_revenue = $totals['total_revenue'];
$total_tickets = $totals['total_tickets'];
$avg_price = $total_tickets > 0 ? $total_revenue / $total_tickets : 0;

// ==========================================
// 4. BIẾN ĐỘNG DOANH THU (So với tháng trước)
// ==========================================
// Nếu người dùng chọn tháng cụ thể thì so sánh với tháng liền trước nó, nếu không thì lấy tháng hiện tại
$target_month = $month_safe ? $month_safe : date('Y-m');
$prev_month = date('Y-m', strtotime($target_month . '-01 - 1 month'));

$fluc_cond_target = "o.status = 'completed' AND DATE_FORMAT(o.created_at, '%Y-%m') = '$target_month'";
$fluc_cond_prev = "o.status = 'completed' AND DATE_FORMAT(o.created_at, '%Y-%m') = '$prev_month'";

if ($cinema_safe) {
    $fluc_cond_target .= " AND o.movie_id IN (SELECT movie_id FROM showtimes WHERE cinema_id = $cinema_safe)";
    $fluc_cond_prev .= " AND o.movie_id IN (SELECT movie_id FROM showtimes WHERE cinema_id = $cinema_safe)";
}

$rev_this_month = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders o WHERE $fluc_cond_target")->fetchColumn();
$rev_last_month = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders o WHERE $fluc_cond_prev")->fetchColumn();

$fluctuation = 0;
if ($rev_last_month > 0) {
    $fluctuation = (($rev_this_month - $rev_last_month) / $rev_last_month) * 100;
} elseif ($rev_this_month > 0) {
    $fluctuation = 100; // Tăng trưởng từ 0
}

// ==========================================
// 5. TỶ LỆ LẤP ĐẦY (Occupancy Rate)
// ==========================================
$s_cond = "1=1";
if ($cinema_safe) $s_cond .= " AND cinema_id = $cinema_safe";
if ($month_safe) $s_cond .= " AND DATE_FORMAT(show_date, '%Y-%m') = '$month_safe'";

$total_shows = $pdo->query("SELECT COUNT(*) FROM showtimes WHERE $s_cond")->fetchColumn() ?: 1;
// Giả lập mỗi phòng chiếu có 40 ghế, nhân với tổng số suất chiếu đã lên lịch
$total_capacity = $total_shows * 40; 
$occupancy_rate = ($total_tickets / $total_capacity) * 100;
$occupancy_rate = min(100, max(0, $occupancy_rate)); // Đảm bảo tỷ lệ nằm trong 0-100%

// ==========================================
// 6. DOANH THU THEO TỪNG PHIM
// ==========================================
$m_cond = "1=1";
if ($cinema_safe) $m_cond .= " AND m.id IN (SELECT movie_id FROM showtimes WHERE cinema_id = $cinema_safe)";

$sql_movies = "
    SELECT m.title, m.poster_url, m.status,
           COALESCE(SUM(IF(o.seat_numbers IS NULL OR o.seat_numbers = '', 0, LENGTH(o.seat_numbers) - LENGTH(REPLACE(o.seat_numbers, ',', '')) + 1)), 0) as tickets_sold,
           COALESCE(SUM(o.total_price), 0) as revenue
    FROM movies m
    LEFT JOIN orders o ON m.id = o.movie_id AND $o_cond
    WHERE $m_cond
    GROUP BY m.id
    ORDER BY revenue DESC
";
$movies_revenue = $pdo->query($sql_movies)->fetchAll();

// ==========================================
// 7. DOANH THU THEO TỪNG RẠP (Đã Fix Lỗi Nhân Bản Dữ Liệu)
// ==========================================
$c_cond = "1=1";
if ($cinema_safe) $c_cond .= " AND c.id = $cinema_safe";

$s_cond_date = "";
if ($month_safe) $s_cond_date = " AND DATE_FORMAT(show_date, '%Y-%m') = '$month_safe'";

$o_cond_date = "";
if ($month_safe) $o_cond_date = " AND DATE_FORMAT(created_at, '%Y-%m') = '$month_safe'";

$sql_cinemas = "
    SELECT 
        c.name, 
        (
            SELECT COUNT(s.id) 
            FROM showtimes s 
            WHERE s.cinema_id = c.id $s_cond_date
        ) as total_shows,
        (
            SELECT COALESCE(SUM(o.total_price), 0) 
            FROM orders o 
            WHERE o.status = 'completed' $o_cond_date
            AND EXISTS (
                SELECT 1 FROM showtimes s 
                WHERE s.cinema_id = c.id 
                  AND s.movie_id = o.movie_id 
                  AND o.show_time = CONCAT(DATE_FORMAT(s.start_time, '%H:%i'), ', ', DATE_FORMAT(s.show_date, '%d/%m/%Y'))
            )
        ) as revenue,
        (
            SELECT COALESCE(SUM(IF(o.seat_numbers IS NULL OR o.seat_numbers = '', 0, LENGTH(o.seat_numbers) - LENGTH(REPLACE(o.seat_numbers, ',', '')) + 1)), 0) 
            FROM orders o 
            WHERE o.status = 'completed' $o_cond_date
            AND EXISTS (
                SELECT 1 FROM showtimes s 
                WHERE s.cinema_id = c.id 
                  AND s.movie_id = o.movie_id 
                  AND o.show_time = CONCAT(DATE_FORMAT(s.start_time, '%H:%i'), ', ', DATE_FORMAT(s.show_date, '%d/%m/%Y'))
            )
        ) as tickets_sold
    FROM cinemas c
    WHERE $c_cond
    ORDER BY revenue DESC
";
$cinemas_revenue = $pdo->query($sql_cinemas)->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Báo cáo doanh thu - CineAdmin</title>
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
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-black text-slate-100 tracking-tight uppercase">Báo cáo doanh thu</h2>
                    <p class="text-slate-400 mt-1">Phân tích dữ liệu bán vé, hiệu suất rạp và theo dõi tăng trưởng.</p>
                </div>
                <a href="export_excel.php?month=<?php echo htmlspecialchars($filter_month); ?>&cinema_id=<?php echo htmlspecialchars($filter_cinema); ?>" target="_blank" class="bg-primary/10 hover:bg-primary/20 text-primary border border-primary/30 px-6 py-3 rounded-xl font-bold flex items-center gap-2 transition-all active:scale-95 inline-flex">
                    <span class="material-symbols-outlined">download</span> Xuất File Excel
                </a>
            </div>

            <form method="GET" action="admin_reports.php" class="bg-surface-dark border border-accent-dark p-4 rounded-2xl mb-8 flex flex-col md:flex-row gap-4 shadow-lg items-end">
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Tháng / Năm</label>
                    <div class="relative">
                        <select name="month" class="w-full bg-background-dark border border-primary/50 hover:border-primary rounded-xl py-3 px-4 pr-10 text-slate-200 focus:border-primary focus:ring-1 focus:ring-primary outline-none appearance-none bg-none cursor-pointer font-medium transition-all">
                            <option class="bg-surface-dark text-slate-400" value="">Tất cả thời gian</option>
                            <?php foreach($months_list as $val => $lbl): ?>
                                <option class="bg-surface-dark text-slate-100" value="<?php echo $val; ?>" <?php echo $filter_month == $val ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-200">expand_more</span>
                    </div>
                </div>
                <div class="flex-1 w-full md:w-auto">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Cụm Rạp</label>
                    <div class="relative">
                        <select name="cinema_id" class="w-full bg-background-dark border border-primary/50 hover:border-primary rounded-xl py-3 px-4 pr-10 text-slate-200 focus:border-primary focus:ring-1 focus:ring-primary outline-none appearance-none bg-none cursor-pointer font-medium transition-all">
                            <option class="bg-surface-dark text-slate-400" value="">Tất cả hệ thống rạp</option>
                            <?php foreach($all_cinemas as $c): ?>
                                <option class="bg-surface-dark text-slate-100" value="<?php echo $c['id']; ?>" <?php echo $filter_cinema == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-200">expand_more</span>
                    </div>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <button type="submit" class="flex-1 md:flex-none bg-primary hover:bg-primary/90 text-background-dark font-bold px-8 py-3 rounded-xl transition-all h-[50px] flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">filter_alt</span> Lọc dữ liệu
                    </button>
                    <?php if(!empty($filter_month) || !empty($filter_cinema)): ?>
                        <a href="admin_reports.php" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 font-bold px-4 py-3 rounded-xl transition-all h-[50px] flex items-center justify-center" title="Xóa bộ lọc">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-4">
                        <span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">payments</span>
                        <?php if($fluctuation != 0): ?>
                        <span class="text-xs font-bold px-2 py-1 rounded-md <?php echo $fluctuation > 0 ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'; ?> flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]"><?php echo $fluctuation > 0 ? 'trending_up' : 'trending_down'; ?></span>
                            <?php echo ($fluctuation > 0 ? '+' : '') . number_format($fluctuation, 1); ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Tổng doanh thu hệ thống</p>
                    <h3 class="text-3xl font-black text-primary mt-1"><?php echo number_format($total_revenue, 0, ',', '.'); ?>đ</h3>
                </div>

                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden">
                    <div class="mb-4">
                        <span class="material-symbols-outlined text-slate-300 bg-slate-700 p-2 rounded-lg">local_activity</span>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Vé đã bán</p>
                    <div class="flex items-end gap-2 mt-1">
                        <h3 class="text-3xl font-black text-slate-100"><?php echo number_format($total_tickets); ?></h3>
                        <span class="text-sm text-slate-500 font-medium mb-1">vé</span>
                    </div>
                </div>

                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden">
                    <div class="mb-4">
                        <span class="material-symbols-outlined text-slate-300 bg-slate-700 p-2 rounded-lg">pie_chart</span>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Doanh thu trung bình / vé</p>
                    <h3 class="text-3xl font-black text-slate-100 mt-1"><?php echo number_format($avg_price, 0, ',', '.'); ?>đ</h3>
                </div>

                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden">
                    <div class="flex justify-between items-start mb-4">
                        <span class="material-symbols-outlined text-slate-300 bg-slate-700 p-2 rounded-lg">event_seat</span>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Tỷ lệ lấp đầy</p>
                    <div class="flex items-center gap-4 mt-1">
                        <h3 class="text-3xl font-black text-slate-100"><?php echo number_format($occupancy_rate, 1); ?>%</h3>
                        <div class="flex-1 h-2 bg-background-dark rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full" style="width: <?php echo $occupancy_rate; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl flex flex-col">
                    <div class="p-6 border-b border-accent-dark bg-background-dark/30 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">movie</span>
                        <h3 class="text-lg font-bold text-slate-100 uppercase tracking-widest">Chi tiết theo Phim</h3>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar flex-1 max-h-[500px]">
                        <table class="w-full text-left border-collapse min-w-[500px]">
                            <thead class="sticky top-0 bg-surface-dark z-10 shadow-sm">
                                <tr class="border-b border-accent-dark text-[10px] font-black text-primary uppercase tracking-widest">
                                    <th class="px-6 py-4">Tên Phim</th>
                                    <th class="px-6 py-4 text-center">Trạng thái</th>
                                    <th class="px-6 py-4 text-center">Vé</th>
                                    <th class="px-6 py-4 text-right">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-dark/50">
                                <?php foreach ($movies_revenue as $movie): ?>
                                <tr class="hover:bg-accent-dark/20 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-12 w-8 bg-slate-800 rounded overflow-hidden flex-shrink-0 border border-accent-dark">
                                                <img class="h-full w-full object-cover" src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster"/>
                                            </div>
                                            <span class="text-sm font-bold text-slate-100 group-hover:text-primary transition-colors max-w-[150px] truncate" title="<?php echo htmlspecialchars($movie['title']); ?>">
                                                <?php echo htmlspecialchars($movie['title']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($movie['status'] == 'now_showing'): ?>
                                            <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase bg-green-500/10 text-green-500">Đang chiếu</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase bg-amber-500/10 text-amber-500">Sắp chiếu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-bold text-slate-300">
                                        <?php echo number_format($movie['tickets_sold']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-sm font-black text-primary"><?php echo number_format($movie['revenue'], 0, ',', '.'); ?>đ</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($movies_revenue) == 0): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Không có dữ liệu trong thời gian này.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl flex flex-col">
                    <div class="p-6 border-b border-accent-dark bg-background-dark/30 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">apartment</span>
                        <h3 class="text-lg font-bold text-slate-100 uppercase tracking-widest">Hiệu suất Rạp chiếu</h3>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar flex-1 max-h-[500px]">
                        <table class="w-full text-left border-collapse min-w-[500px]">
                            <thead class="sticky top-0 bg-surface-dark z-10 shadow-sm">
                                <tr class="border-b border-accent-dark text-[10px] font-black text-primary uppercase tracking-widest">
                                    <th class="px-6 py-4">Tên Cụm Rạp</th>
                                    <th class="px-6 py-4 text-center">Suất chiếu</th>
                                    <th class="px-6 py-4 text-center">Vé</th>
                                    <th class="px-6 py-4 text-right">Doanh thu đóng góp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-dark/50">
                                <?php foreach ($cinemas_revenue as $cinema): ?>
                                <tr class="hover:bg-accent-dark/20 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="size-8 rounded bg-accent-dark flex items-center justify-center text-primary font-bold border border-primary/20 shrink-0">
                                                <?php echo mb_substr(trim($cinema['name']), 0, 1, "UTF-8"); ?>
                                            </div>
                                            <span class="text-sm font-bold text-slate-100 group-hover:text-primary transition-colors max-w-[150px] truncate" title="<?php echo htmlspecialchars($cinema['name']); ?>">
                                                <?php echo htmlspecialchars($cinema['name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-medium text-slate-400">
                                        <?php echo number_format($cinema['total_shows']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-bold text-slate-300">
                                        <?php echo number_format($cinema['tickets_sold']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="text-sm font-black text-primary"><?php echo number_format($cinema['revenue'], 0, ',', '.'); ?>đ</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($cinemas_revenue) == 0): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Không có dữ liệu trong thời gian này.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>
</div>
</body>
</html>