<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// 1. Lấy danh sách Rạp để hiển thị ra Bộ lọc
$cinemas = $pdo->query("SELECT id, name FROM cinemas ORDER BY name ASC")->fetchAll();

// 2. Xử lý Xóa lịch chiếu
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM showtimes WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: admin_showtimes.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Không thể xóa lịch chiếu này.";
    }
}

// 3. Xử lý Tìm kiếm và Bộ lọc
$search_query = $_GET['search'] ?? '';
$filter_cinema = $_GET['cinema_id'] ?? '';
$filter_date = $_GET['date'] ?? '';

$where_sql = "1=1";
$params = [];

if (!empty($search_query)) {
    $where_sql .= " AND (m.title LIKE :search OR c.name LIKE :search OR s.room_name LIKE :search)";
    $params[':search'] = "%" . $search_query . "%";
}

if (!empty($filter_cinema)) {
    $where_sql .= " AND s.cinema_id = :cinema_id";
    $params[':cinema_id'] = $filter_cinema;
}

if (!empty($filter_date)) {
    // Lọc chính xác theo ngày được chọn từ lịch
    $where_sql .= " AND s.show_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}

// 4. Lấy danh sách lịch chiếu
$sql = "SELECT s.*, m.title as movie_title, m.poster_url, c.name as cinema_name 
        FROM showtimes s
        JOIN movies m ON s.movie_id = m.id
        JOIN cinemas c ON s.cinema_id = c.id
        WHERE $where_sql
        ORDER BY s.show_date DESC, s.start_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$showtimes = $stmt->fetchAll();

// ==============================================================
// 5. THỐNG KÊ SỐ LƯỢNG GHẾ ĐÃ ĐẶT TỪ BẢNG ORDERS (ĐÃ FIX LỖI CINEMA_ID)
// ==============================================================
$stmt_orders = $pdo->query("SELECT cinema_id, movie_id, show_time, room_name, seat_numbers FROM orders WHERE status = 'completed'");
$orders_data = $stmt_orders->fetchAll();

$booked_counts = [];
foreach ($orders_data as $od) {
    // SỬA Ở ĐÂY: Thêm ID Rạp vào đầu Key để phân biệt rạch ròi
    $key = $od['cinema_id'] . '_' . $od['movie_id'] . '_' . trim($od['show_time']) . '_' . trim($od['room_name']);
    if (!isset($booked_counts[$key])) $booked_counts[$key] = 0;
    
    $seats = array_filter(explode(',', $od['seat_numbers']));
    foreach ($seats as $seat) {
        if (strpos(trim($seat), 'J') === 0) $booked_counts[$key] += 2;
        else $booked_counts[$key] += 1;
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Quản lý lịch chiếu - CineAdmin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
        input:-webkit-autofill { -webkit-box-shadow: 0 0 0 30px #2a2614 inset !important; -webkit-text-fill-color: #e2e8f0 !important; }
    </style>
</head>
<body class="bg-background-dark text-slate-100 min-h-screen">
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
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="bg-green-500/10 text-green-500 px-4 py-3 rounded-xl mb-6 font-medium border border-green-500/20">Xóa lịch chiếu thành công!</div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-slate-100 uppercase tracking-tight">Quản lý lịch chiếu</h1>
                    <p class="text-slate-400 mt-1">Theo dõi và sắp xếp lịch chiếu phim tại hệ thống các cụm rạp.</p>
                </div>
                <a href="add_showtime.php" class="bg-primary hover:bg-primary/90 text-background-dark px-6 py-3 rounded-xl font-bold flex items-center gap-2 shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] transition-all active:scale-95">
                    <span class="material-symbols-outlined">add_circle</span> Tạo lịch chiếu
                </a>
            </div>

            <form method="GET" action="admin_showtimes.php" class="bg-surface-dark border border-accent-dark p-4 rounded-2xl mb-8 flex flex-col lg:flex-row gap-4 shadow-lg">
                <div class="flex-1 relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off" placeholder="Tìm tên phim, phòng chiếu..." class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 pl-10 pr-4 text-slate-200 focus:border-primary outline-none">
                </div>
                
                <div class="flex flex-wrap gap-4">
                    <select name="cinema_id" class="bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-200 focus:border-primary outline-none min-w-[180px]">
                        <option class="bg-surface-dark text-slate-100" value="">Tất cả rạp</option>
                        <?php foreach($cinemas as $c): ?>
                            <option class="bg-surface-dark text-slate-100" value="<?php echo $c['id']; ?>" <?php echo $filter_cinema == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" 
                        class="bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-200 focus:border-primary outline-none min-w-[180px]" 
                        style="color-scheme: dark;" 
                        title="Chọn ngày chiếu">

                    <button type="submit" class="bg-accent-dark hover:bg-primary hover:text-background-dark text-slate-200 font-bold px-6 py-3 rounded-xl transition-all">Áp dụng</button>
                    
                    <?php if(!empty($_GET)): ?>
                        <a href="admin_showtimes.php" class="bg-red-500/10 text-red-500 font-bold px-4 py-3 rounded-xl flex items-center justify-center"><span class="material-symbols-outlined">close</span></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-accent-dark/30 border-b border-accent-dark text-xs font-bold uppercase tracking-widest text-slate-400">
                                <th class="px-6 py-4">Phim</th>
                                <th class="px-6 py-4">Cụm Rạp</th>
                                <th class="px-6 py-4 text-center">Phòng</th>
                                <th class="px-6 py-4 text-center">Thời gian</th>
                                <th class="px-6 py-4 text-center">Tình trạng vé</th>
                                <th class="px-6 py-4 text-center">Trạng thái</th>
                                <th class="px-6 py-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-dark/50">
                            <?php foreach ($showtimes as $st): 
                                // SỬA Ở ĐÂY: Thêm ID Rạp vào biến Key
                                $time_str = date('H:i', strtotime($st['start_time'])) . ', ' . date('d/m/Y', strtotime($st['show_date']));
                                $key = $st['cinema_id'] . '_' . $st['movie_id'] . '_' . $time_str . '_' . trim($st['room_name']);
                                
                                $total_seats = 100; // Cố định 40 ghế / phòng
                                $booked_seats = $booked_counts[$key] ?? 0;
                                $is_full = ($booked_seats >= $total_seats);
                                $percent = ($booked_seats / $total_seats) * 100;
                            ?>
                            <tr class="hover:bg-accent-dark/20 transition-colors group <?php echo $is_full ? 'bg-red-500/5' : ''; ?>">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-14 w-10 bg-slate-800 rounded overflow-hidden flex-shrink-0 border border-accent-dark">
                                            <img class="h-full w-full object-cover" src="<?php echo htmlspecialchars($st['poster_url']); ?>" alt="Poster"/>
                                        </div>
                                        <span class="text-slate-100 font-bold group-hover:text-primary transition-colors max-w-[180px] truncate" title="<?php echo htmlspecialchars($st['movie_title']); ?>">
                                            <?php echo htmlspecialchars($st['movie_title']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-300 text-sm"><?php echo htmlspecialchars($st['cinema_name']); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-3 py-1 bg-background-dark/50 border border-accent-dark rounded-lg font-mono text-sm text-slate-300">
                                        <?php echo htmlspecialchars($st['room_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <span class="text-slate-100 font-bold text-sm">
                                            <?php echo date('H:i', strtotime($st['start_time'])) . ' - ' . date('H:i', strtotime($st['end_time'])); ?>
                                        </span>
                                        <span class="text-primary text-[10px] uppercase tracking-widest mt-1">Ngày: <?php echo date('d/m/Y', strtotime($st['show_date'])); ?></span>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center justify-center gap-1.5">
                                        <span class="text-xs font-bold tracking-wider <?php echo $is_full ? 'text-red-500' : ($booked_seats > 0 ? 'text-primary' : 'text-slate-400'); ?>">
                                            <?php echo $booked_seats; ?> / <?php echo $total_seats; ?>
                                        </span>
                                        <div class="w-20 h-1.5 bg-background-dark rounded-full overflow-hidden border border-accent-dark/50">
                                            <div class="h-full <?php echo $is_full ? 'bg-red-500' : 'bg-primary'; ?>" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <?php if ($is_full || $st['status'] == 'sold_out'): ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-red-500/10 text-red-500 border border-red-500/20">Hết vé</span>
                                    <?php elseif ($st['status'] == 'showing'): ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-green-500/10 text-green-500 border border-green-500/20">Đang chiếu</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-primary/10 text-primary border border-primary/20">Sắp chiếu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="admin_showtimes.php?action=delete&id=<?php echo $st['id']; ?>" onclick="return confirm('Xóa lịch chiếu này?');" class="inline-block p-2 rounded-lg text-slate-400 hover:bg-red-500/10 hover:text-red-500 transition-all" title="Xóa">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(count($showtimes) == 0): ?>
                            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">Chưa có lịch chiếu nào phù hợp.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>