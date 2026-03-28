<?php
session_start();
require_once 'includes/db_connect.php';

// YÊU CẦU ĐĂNG NHẬP: Bắt buộc khách hàng phải login trước khi chọn suất
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$movie_id = $_GET['id'] ?? 0;

// 1. Lấy thông tin phim
$stmt_movie = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt_movie->execute([$movie_id]);
$movie = $stmt_movie->fetch();

if (!$movie) {
    die("Không tìm thấy thông tin phim.");
}

// 2. Lấy danh sách suất chiếu của phim này
$stmt_shows = $pdo->prepare("
    SELECT s.*, c.name as cinema_name 
    FROM showtimes s
    LEFT JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.movie_id = ? AND s.show_date >= CURRENT_DATE()
    ORDER BY s.show_date ASC, s.start_time ASC
");
$stmt_shows->execute([$movie_id]);
$showtimes = $stmt_shows->fetchAll();

// ==============================================================
// ĐÃ FIX LỖI: LẤY THÊM TÊN PHÒNG ĐỂ TRÁNH TRÙNG LẶP SỐ VÉ GIỮA CÁC RẠP
// ==============================================================
$stmt_booked = $pdo->prepare("SELECT show_time, room_name, seat_numbers FROM orders WHERE movie_id = ? AND status = 'completed'");
$stmt_booked->execute([$movie_id]);
$all_orders = $stmt_booked->fetchAll();

$booked_counts = [];
foreach ($all_orders as $order) {
    // TẠO KEY DUY NHẤT = GIỜ CHIẾU + TÊN PHÒNG
    $time_room_key = trim($order['show_time']) . '|' . trim($order['room_name']); 
    
    if (!isset($booked_counts[$time_room_key])) {
        $booked_counts[$time_room_key] = 0;
    }
    // Đếm số lượng ghế (Ghế J là ghế đôi -> tính 2 chỗ/người)
    $seats = array_filter(explode(',', $order['seat_numbers']));
    foreach ($seats as $seat) {
        $seat_name = trim($seat);
        // Nếu tên ghế bắt đầu bằng chữ 'J' thì cộng 2
        if (strpos($seat_name, 'J') === 0) {
            $booked_counts[$time_room_key] += 2;
        } else {
            $booked_counts[$time_room_key] += 1;
        }
    }
}

// 3. Nhóm suất chiếu theo Ngày -> Cụm Rạp
$grouped_shows = [];
foreach ($showtimes as $show) {
    $date = $show['show_date'];
    $cinema = $show['cinema_name'] ?? 'Hệ thống rạp';
    $grouped_shows[$date][$cinema][] = $show;
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Chọn suất chiếu - Gold Cinema</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180a", "surface-dark": "#2a2614", "border-dark": "#494222", "accent-dark": "#403a1e" } } } }
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
            <a href="javascript:history.back()" class="text-slate-300 hover:text-primary transition-colors text-sm font-bold flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Quay lại
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full px-6 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            
            <div class="md:col-span-1">
                <div class="bg-surface-dark border border-border-dark rounded-2xl p-6 shadow-xl sticky top-28">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster" class="w-full rounded-xl shadow-lg border border-border-dark mb-6">
                    <span class="px-3 py-1 bg-primary/20 text-primary text-[10px] font-black uppercase tracking-widest rounded-md border border-primary/30">Phim đang chiếu</span>
                    <h2 class="text-2xl font-black text-slate-100 uppercase tracking-tight mt-3 mb-2"><?php echo htmlspecialchars($movie['title']); ?></h2>
                    <p class="text-sm text-slate-400 font-medium mb-4"><?php echo htmlspecialchars($movie['genre']); ?></p>
                    <div class="flex items-center gap-2 text-primary">
                        <span class="material-symbols-outlined">schedule</span>
                        <span class="text-sm font-bold"><?php echo htmlspecialchars($movie['duration'] ?? '120'); ?> phút</span>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <h2 class="text-3xl font-black uppercase tracking-tight mb-8 border-l-4 border-primary pl-4">Chọn suất chiếu</h2>

                <?php if (count($grouped_shows) > 0): ?>
                    <div class="space-y-8">
                        <?php foreach ($grouped_shows as $date => $cinemas): 
                            $date_display = date('d/m/Y', strtotime($date));
                            if ($date == date('Y-m-d')) {
                                $date_display = "Hôm nay, " . $date_display;
                            }
                        ?>
                        <div class="bg-surface-dark border border-border-dark rounded-2xl p-6 shadow-lg">
                            <h3 class="text-xl font-bold text-primary mb-6 flex items-center gap-2 border-b border-border-dark pb-4">
                                <span class="material-symbols-outlined">calendar_month</span> <?php echo $date_display; ?>
                            </h3>
                            
                            <div class="space-y-6">
                                <?php foreach ($cinemas as $cinema_name => $shows): ?>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-300 mb-3 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">apartment</span> <?php echo htmlspecialchars($cinema_name); ?>
                                    </h4>
                                    <div class="flex flex-wrap gap-3">
                                        <?php foreach ($shows as $show): 
                                            $time = isset($show['start_time']) ? date('H:i', strtotime($show['start_time'])) : 'Đang cập nhật';
                                            $room = $show['room_name'] ?? 'Phòng Tiêu Chuẩn';
                                            $full_show_time = $time . ', ' . date('d/m/Y', strtotime($date));
                                            
                                            // TÍNH TOÁN SỐ GHẾ TRỐNG DỰA TRÊN CẢ GIỜ VÀ TÊN PHÒNG
                                            $check_key = $full_show_time . '|' . trim($room);
                                            $booked_seats = $booked_counts[$check_key] ?? 0;
                                            
                                            $total_seats = 100; // Cố định 40 ghế/phòng
                                            $available_seats = $total_seats - $booked_seats;
                                            if ($available_seats < 0) $available_seats = 0;
                                            
                                            $is_full = ($available_seats == 0);
                                        ?>
                                            
                                            <?php if ($is_full): ?>
                                                <div class="px-5 py-2 w-[100px] bg-background-dark/50 border border-red-500/30 rounded-xl text-slate-500 flex flex-col items-center justify-center cursor-not-allowed opacity-70">
                                                    <span class="font-bold line-through text-lg"><?php echo $time; ?></span>
                                                    <span class="text-[9px] text-red-500 uppercase tracking-widest mt-0.5 font-bold">Hết vé</span>
                                                </div>
                                            <?php else: ?>
                                                <a href="chonGhe.php?id=<?php echo $movie_id; ?>&show_id=<?php echo $show['id']; ?>&time=<?php echo urlencode($full_show_time); ?>&room=<?php echo urlencode($room); ?>" 
                                                   class="px-5 py-2 w-[100px] bg-background-dark border border-accent-dark hover:border-primary rounded-xl text-slate-200 hover:text-primary transition-all shadow-sm hover:shadow-[0_0_15px_rgba(242,204,13,0.3)] hover:-translate-y-1 flex flex-col items-center justify-center group">
                                                    <span class="font-bold text-lg"><?php echo $time; ?></span>
                                                    <span class="text-[9px] text-slate-500 group-hover:text-primary/80 uppercase tracking-widest mt-0.5 font-medium">
                                                        <?php echo $available_seats; ?>/<?php echo $total_seats; ?> ghế
                                                    </span>
                                                </a>
                                            <?php endif; ?>

                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-surface-dark border border-border-dark rounded-2xl p-16 text-center flex flex-col items-center">
                        <span class="material-symbols-outlined text-6xl text-slate-600 mb-4">event_busy</span>
                        <h3 class="text-xl font-bold text-slate-200 mb-2">Chưa có lịch chiếu</h3>
                        <p class="text-slate-400">Bộ phim này hiện chưa được xếp lịch chiếu trong thời gian tới. Vui lòng quay lại sau!</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</body>
</html>