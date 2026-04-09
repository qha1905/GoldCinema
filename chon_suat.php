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
// --- XỬ LÝ MODULE ĐÁNH GIÁ (REVIEW) ---
// ==============================================================
$can_review = false;
if (isset($_SESSION["user_logged_in"])) {
    // Kiểm tra xem khách hàng ĐÃ MUA VÉ phim này chưa
    $stmt_check = $pdo->prepare("SELECT id FROM orders WHERE user_id = ? AND movie_id = ? AND status = 'completed' LIMIT 1");
    $stmt_check->execute([$_SESSION["user_id"], $movie_id]);
    if ($stmt_check->fetch()) $can_review = true;

    // Xử lý khi khách hàng gửi Review
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        if ($can_review && $rating >= 1 && $rating <= 5 && !empty($comment)) {
            $stmt_insert = $pdo->prepare("INSERT INTO reviews (user_id, movie_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$_SESSION["user_id"], $movie_id, $rating, $comment]);
            header("Location: chon_suat.php?id=" . $movie_id . "#reviews-section");
            exit;
        }
    }
}

// Lấy danh sách tất cả các bình luận của phim này
$stmt_reviews = $pdo->prepare("SELECT r.*, u.fullname FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.movie_id = ? ORDER BY r.created_at DESC");
$stmt_reviews->execute([$movie_id]);
$reviews = $stmt_reviews->fetchAll();
// ==============================================================

// ==============================================================
// ĐÃ FIX LỖI: LẤY THÊM ID RẠP (CINEMA_ID) ĐỂ TRÁNH TRÙNG LẶP CHÉO
// ==============================================================
$stmt_booked = $pdo->prepare("SELECT cinema_id, show_time, room_name, seat_numbers FROM orders WHERE movie_id = ? AND status = 'completed'");
$stmt_booked->execute([$movie_id]);
$all_orders = $stmt_booked->fetchAll();

$booked_counts = [];
foreach ($all_orders as $order) {
    // TẠO KEY DUY NHẤT = ID RẠP + GIỜ CHIẾU + TÊN PHÒNG
    $time_room_key = $order['cinema_id'] . '|' . trim($order['show_time']) . '|' . trim($order['room_name']); 
    
    if (!isset($booked_counts[$time_room_key])) $booked_counts[$time_room_key] = 0;
    
    // Đếm số lượng ghế (Ghế J tính 2 chỗ)
    $seats = array_filter(explode(',', $order['seat_numbers']));
    foreach ($seats as $seat) {
        if (strpos(trim($seat), 'J') === 0) $booked_counts[$time_room_key] += 2;
        else $booked_counts[$time_room_key] += 1;
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
            <a href="index.php" class="flex items-center gap-3 hover:scale-105 transition-transform">
                <img src="images/my_logo.png" alt="Logo Rạp Phim Của Tôi" class="h-12 w-12 object-cover rounded-full shadow-md border border-primary/30">
                <h1 class="text-2xl font-black tracking-tight text-primary uppercase">H Cinema</h1>
            </a>
            <a href="index.php" class="text-slate-300 hover:text-primary transition-colors text-sm font-bold flex items-center gap-1">
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
                                            
                                            // TÍNH TOÁN SỐ GHẾ TRỐNG DỰA TRÊN ID RẠP + GIỜ VÀ TÊN PHÒNG
                                            $check_key = $show['cinema_id'] . '|' . $full_show_time . '|' . trim($room);
                                            $booked_seats = $booked_counts[$check_key] ?? 0;
                                            
                                            $total_seats = 100; // Cố định 100 ghế/phòng
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

        <div id="reviews-section" class="mt-12 border-t border-border-dark pt-12">
            <h2 class="text-3xl font-black uppercase tracking-tight mb-8 border-l-4 border-primary pl-4">Đánh giá từ khán giả</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div class="md:col-span-1">
                    <?php if ($can_review): ?>
                        <form method="POST" class="bg-surface-dark border border-border-dark rounded-2xl p-6 sticky top-28 shadow-lg">
                            <h3 class="font-bold text-primary mb-4">Gửi đánh giá của bạn</h3>
                            <div class="mb-4">
                                <label class="text-xs font-bold text-slate-400 block mb-2">Điểm đánh giá (1-5 sao)</label>
                                <select name="rating" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                                    <option value="5">⭐⭐⭐⭐⭐ Tuyệt vời</option>
                                    <option value="4">⭐⭐⭐⭐ Khá hay</option>
                                    <option value="3">⭐⭐⭐ Bình thường</option>
                                    <option value="2">⭐⭐ Tệ</option>
                                    <option value="1">⭐ Rất tệ</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="text-xs font-bold text-slate-400 block mb-2">Cảm nhận của bạn</label>
                                <textarea name="comment" required rows="3" class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary placeholder:text-slate-600" placeholder="Bộ phim này thế nào?"></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="w-full bg-primary text-background-dark font-bold py-3 rounded-xl hover:bg-primary/90 transition-all">Gửi Đánh Giá</button>
                        </form>
                    <?php else: ?>
                        <div class="bg-background-dark border border-accent-dark rounded-2xl p-6 text-center border-dashed">
                            <span class="material-symbols-outlined text-4xl text-slate-600 mb-2">verified_user</span>
                            <p class="text-sm text-slate-400">Chỉ những khách hàng <strong class="text-primary">đã mua vé xem phim này</strong> mới có quyền gửi đánh giá để đảm bảo tính minh bạch.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="md:col-span-2 space-y-4">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="bg-surface-dark border border-border-dark rounded-xl p-5">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-accent-dark flex items-center justify-center text-primary font-bold">
                                            <?php echo mb_substr(trim($rev['fullname']), 0, 1, "UTF-8"); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-200 text-sm flex items-center gap-1">
                                                <?php echo htmlspecialchars($rev['fullname']); ?>
                                                <span class="material-symbols-outlined text-[14px] text-blue-500" title="Đã mua vé">verified</span>
                                            </p>
                                            <p class="text-[10px] text-slate-500"><?php echo date('d/m/Y H:i', strtotime($rev['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-primary text-sm tracking-widest">
                                        <?php echo str_repeat('⭐', $rev['rating']); ?>
                                    </div>
                                </div>
                                <p class="text-slate-300 text-sm mt-3 ml-13 leading-relaxed"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-slate-500 italic">Chưa có đánh giá nào cho phim này. Hãy là người đầu tiên!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </main>
</body>
</html>