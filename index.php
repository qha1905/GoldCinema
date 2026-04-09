<?php
session_start();
require_once 'includes/db_connect.php';

// 1. TÌM 5 PHIM BÁN CHẠY NHẤT LÀM BANNER (Top 5)
$stmt_top = $pdo->query("
    SELECT m.*, 
           COALESCE(SUM(IF(o.seat_numbers IS NULL OR o.seat_numbers = '', 0, LENGTH(o.seat_numbers) - LENGTH(REPLACE(o.seat_numbers, ',', '')) + 1)), 0) as tickets_sold
    FROM movies m 
    LEFT JOIN orders o ON m.id = o.movie_id AND o.status = 'completed'
    WHERE m.status = 'now_showing'
    GROUP BY m.id 
    ORDER BY tickets_sold DESC 
    LIMIT 5
");
$top_movies = $stmt_top->fetchAll();

// 2. LẤY DANH SÁCH RẠP CHO MENU DROPDOWN
$stmt_cinemas = $pdo->query("SELECT id, name FROM cinemas WHERE status = 'active' ORDER BY name ASC");
$nav_cinemas = $stmt_cinemas->fetchAll();

// 3. XỬ LÝ LOGIC TÌM KIẾM, LỌC VÀ XEM TẤT CẢ
$search_query = $_GET['search'] ?? '';
$filter_genre = $_GET['genre'] ?? '';
$filter_cinema = $_GET['cinema_id'] ?? '';
$view_all = $_GET['view_all'] ?? '';

$where_sql = "";
$join_sql = "";
$params = [];
$select_cols = "m.*";
$selected_cinema_name = "";

// Lọc theo từ khóa
if (!empty($search_query)) {
    $where_sql .= " AND (m.title LIKE :search OR m.description LIKE :search)";
    $params[':search'] = "%" . $search_query . "%";
}

// Lọc theo thể loại
if (!empty($filter_genre)) {
    $where_sql .= " AND m.genre LIKE :genre";
    $params[':genre'] = "%" . $filter_genre . "%";
}

// Lọc theo Rạp (JOIN với bảng showtimes)
if (!empty($filter_cinema)) {
    $join_sql = " JOIN showtimes s ON m.id = s.movie_id ";
    $where_sql .= " AND s.cinema_id = :cinema_id";
    $params[':cinema_id'] = $filter_cinema;
    $select_cols = "DISTINCT m.*"; // Dùng DISTINCT để phim không bị lặp lại nếu có nhiều suất chiếu

    // Lấy tên rạp để hiển thị ra thông báo
    foreach ($nav_cinemas as $c) {
        if ($c['id'] == $filter_cinema) {
            $selected_cinema_name = $c['name'];
            break;
        }
    }
}

// Xác định xem có đang dùng bất kỳ bộ lọc nào không
$is_filtering = !empty($search_query) || !empty($filter_genre) || !empty($filter_cinema);

// Lấy Phim Đang Chiếu (Nếu đang lọc hoặc bấm xem tất cả thì bỏ LIMIT 4)
$limit_now = ($is_filtering || $view_all == 'now_showing') ? "" : "LIMIT 4";
$stmt_now = $pdo->prepare("SELECT $select_cols FROM movies m $join_sql WHERE m.status = 'now_showing' $where_sql ORDER BY m.release_date DESC $limit_now");
$stmt_now->execute($params);
$now_showing = $stmt_now->fetchAll();

// Lấy Phim Sắp Chiếu (Nếu đang lọc hoặc bấm xem tất cả thì bỏ LIMIT 4)
$limit_coming = ($is_filtering || $view_all == 'coming_soon') ? "" : "LIMIT 4";
$stmt_coming = $pdo->prepare("SELECT $select_cols FROM movies m $join_sql WHERE m.status = 'coming_soon' $where_sql ORDER BY m.release_date ASC $limit_coming");
$stmt_coming->execute($params);
$coming_soon = $stmt_coming->fetchAll();


// 4. LẤY DANH SÁCH THỂ LOẠI (Cho Select Box của Bộ lọc)
$stmt_genres = $pdo->query("SELECT DISTINCT genre FROM movies WHERE genre != ''");
$all_genres = [];
while ($row = $stmt_genres->fetch()) {
    $genres = explode(',', $row['genre']);
    foreach ($genres as $g) {
        $g = trim($g);
        if (!in_array($g, $all_genres)) $all_genres[] = $g;
    }
}
sort($all_genres); // Sắp xếp theo Alpha B

// 5. TÍNH TOÁN HẠNG THÀNH VIÊN ĐỂ HIỂN THỊ LÊN HEADER
$user_rank_display = 'HẠNG BẠC'; // Mặc định
if (isset($_SESSION["user_logged_in"]) && $_SESSION["role"] !== 'admin') {
    $stmt_rank = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE user_id = ? AND status = 'completed'");
    $stmt_rank->execute([$_SESSION["user_id"]]);
    $total_spent = $stmt_rank->fetchColumn() ?: 0;
    
    if ($total_spent >= 2000000) {
        $user_rank_display = 'HẠNG PLATINUM';
    } elseif ($total_spent >= 500000) {
        $user_rank_display = 'HẠNG VÀNG';
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Gold Cinema - Trải nghiệm điện ảnh đỉnh cao</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2cc0d",
                        "background-light": "#f8f8f5",
                        "background-dark": "#1a180a",
                        "surface-dark": "#2a2614",
                        "border-dark": "#494222",
                    },
                    fontFamily: {"display": ["Be Vietnam Pro", "sans-serif"]}
                },
            },
        }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180a; }
        .hero-gradient {
            background: linear-gradient(to right, #1a180a 0%, rgba(26, 24, 10, 0.8) 40%, rgba(26, 24, 10, 0.1) 100%),
                        linear-gradient(to top, #1a180a 0%, transparent 30%);
        }
        .dropdown-bridge::before {
            content: ''; position: absolute; top: -15px; left: 0; right: 0; height: 15px; background: transparent;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1a180a; }
        ::-webkit-scrollbar-thumb { background: #494222; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #f2cc0d; }

        /* Fix lỗi nền trắng khi chọn gợi ý (Autofill trình duyệt) */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 50px #2a2614 inset !important; /* Màu #2a2614 khớp với màu bg-surface-dark của form */
            -webkit-text-fill-color: #e2e8f0 !important; /* Giữ chữ màu sáng (slate-200) */
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col relative overflow-x-hidden">

    <header class="fixed top-0 inset-x-0 z-50 bg-background-dark/80 backdrop-blur-md border-b border-border-dark transition-all duration-300">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3 text-primary mb-2 hover:scale-105 transition-transform">
                    <img src="images/my_logo.png" alt="Logo Rạp Phim Của Tôi" class="h-12 w-12 object-cover rounded-full shadow-md border border-primary/30">
                    <h1 class="text-3xl font-black tracking-tighter uppercase italic">H CINEMA</h1>
            </a>

            <nav class="hidden md:flex items-center gap-8">
                <a href="#now-showing" class="text-slate-300 hover:text-primary font-bold text-sm transition-colors">Phim Đang Chiếu</a>
                <a href="#coming-soon" class="text-slate-300 hover:text-primary font-bold text-sm transition-colors">Phim Sắp Chiếu</a>
                
                <div class="relative group">
                    <a href="#" class="text-slate-300 hover:text-primary font-bold text-sm transition-colors flex items-center gap-1 cursor-pointer py-4">
                        Rạp <span class="material-symbols-outlined text-[16px]">expand_more</span>
                    </a>
                    <div class="dropdown-bridge absolute left-0 top-[90%] w-64 bg-surface-dark border border-border-dark rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all flex flex-col overflow-hidden transform origin-top-left scale-95 group-hover:scale-100">
                        <?php if(count($nav_cinemas) > 0): ?>
                            <?php foreach ($nav_cinemas as $c): ?>
                                <a href="index.php?cinema_id=<?php echo $c['id']; ?>#filter-section" class="px-5 py-3 hover:bg-primary/10 text-sm font-medium text-slate-200 hover:text-primary transition-colors border-b border-border-dark last:border-0 truncate">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="px-5 py-3 text-sm text-slate-400 italic">Đang cập nhật rạp...</p>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="#" class="text-slate-300 hover:text-primary font-bold text-sm transition-colors">Ưu Đãi</a>
            </nav>

            <div class="flex items-center gap-6">
                <?php if (isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"] === true): ?>
                    <div class="flex items-center gap-3 cursor-pointer group relative py-4">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-bold text-slate-100"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name']))[count(explode(' ', trim($_SESSION['user_name']))) - 1]); ?></p>
                            <?php if ($_SESSION["role"] === 'admin'): ?>
                                <p class="text-[10px] text-primary font-black uppercase tracking-widest">Admin</p>
                            <?php else: ?>
                                <p class="text-[10px] text-primary font-black uppercase tracking-widest"><?php echo $user_rank_display; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="w-10 h-10 rounded-full border-2 border-primary flex items-center justify-center bg-surface-dark text-primary font-bold">
                            <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                        </div>
                        
                        <div class="dropdown-bridge absolute right-0 top-[90%] w-48 bg-surface-dark border border-border-dark rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all flex flex-col overflow-hidden transform origin-top-right scale-95 group-hover:scale-100">
                            <?php if ($_SESSION["role"] === 'admin'): ?>
                                <a href="admin_dashboard.php" class="px-4 py-3 hover:bg-primary/10 text-sm font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">dashboard</span> Quản trị viên</a>
                            <?php endif; ?>
                            
                            <a href="ve_cua_toi.php" class="px-4 py-3 hover:bg-primary/10 text-sm font-medium flex items-center gap-2 text-slate-200"><span class="material-symbols-outlined text-[18px]">confirmation_number</span> Vé của tôi</a>
                            <a href="tai_khoan.php" class="px-4 py-3 hover:bg-primary/10 text-sm font-medium flex items-center gap-2 text-slate-200"><span class="material-symbols-outlined text-[18px]">manage_accounts</span> Tài khoản</a>
                            
                            <a href="logout.php" class="px-4 py-3 hover:bg-red-500/10 text-red-500 text-sm font-medium flex items-center gap-2 border-t border-border-dark"><span class="material-symbols-outlined text-[18px]">logout</span> Đăng xuất</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="bg-primary text-background-dark px-6 py-2.5 rounded-full font-bold text-sm hover:brightness-110 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-1 flex flex-col">
        
        <?php if (!empty($top_movies) && !$is_filtering && empty($view_all)): ?>
        <section class="relative w-full h-[80vh] min-h-[600px] mt-0 overflow-hidden bg-background-dark" id="banner-slider">
            
            <?php foreach ($top_movies as $index => $movie): 
                // THUẬT TOÁN ƯU TIÊN ẢNH NGANG: Tìm banner_url, nếu không có mới dùng poster_url
                $bg_image = !empty($movie['banner_url']) ? $movie['banner_url'] : $movie['poster_url'];
            ?>
                <div class="slide absolute inset-0 w-full h-full transition-opacity duration-1000 ease-in-out <?php echo $index === 0 ? 'opacity-100 z-10 pointer-events-auto' : 'opacity-0 z-0 pointer-events-none'; ?>" data-index="<?php echo $index; ?>">
                    
                    <div class="absolute inset-0 w-full h-full">
                        <img src="<?php echo htmlspecialchars($bg_image); ?>" alt="Banner" class="w-full h-full object-cover object-center">
                        <div class="absolute inset-0 hero-gradient"></div>
                    </div>

                    <div class="absolute inset-0 w-full h-full flex items-center">
                        <div class="relative z-20 max-w-7xl mx-auto w-full px-6 flex flex-col justify-center mt-10 md:mt-20">
                            
                            <span class="bg-primary text-background-dark text-xs font-black uppercase tracking-widest px-3 py-1 rounded-full w-fit mb-4 shadow-lg shadow-primary/20">
                                Top <?php echo $index + 1; ?> Doanh Thu
                            </span>
                            
                            <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-white mb-3 uppercase tracking-tighter drop-shadow-2xl max-w-3xl leading-tight">
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </h1>

                            <div class="flex flex-wrap items-center gap-3 mb-4 text-xs md:text-sm font-bold text-slate-200">
                                <span class="bg-surface-dark/60 backdrop-blur-md border border-border-dark px-3 py-1.5 rounded-lg text-primary">
                                    <?php echo htmlspecialchars($movie['genre'] ?? 'Đang cập nhật'); ?>
                                </span>
                                <span class="flex items-center gap-1 bg-surface-dark/60 backdrop-blur-md border border-border-dark px-3 py-1.5 rounded-lg">
                                    <span class="material-symbols-outlined text-[16px] md:text-[18px] text-primary">schedule</span> <?php echo htmlspecialchars($movie['duration'] ?? '120'); ?> phút
                                </span>
                            </div>
                            
                            <p class="text-slate-300 text-sm md:text-base lg:text-lg max-w-xl mb-8 line-clamp-3 drop-shadow-md">
                                <?php echo htmlspecialchars($movie['description']); ?>
                            </p>
                            
                            <div class="flex flex-wrap items-center gap-4">
                                <a href="chon_suat.php?id=<?php echo $movie['id']; ?>" class="bg-primary text-background-dark px-6 md:px-8 py-3 md:py-4 rounded-xl font-bold text-base md:text-lg flex items-center gap-2 hover:bg-primary/90 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.4)] active:scale-95">
                                    <span class="material-symbols-outlined">confirmation_number</span> ĐẶT VÉ NGAY
                                </a>
                                <button onclick="openTrailerModal('<?php echo htmlspecialchars($movie['trailer_url'] ?? ''); ?>')" class="border-2 border-slate-300 text-slate-100 hover:border-primary hover:text-primary px-6 md:px-8 py-3 md:py-4 rounded-xl font-bold text-base md:text-lg flex items-center gap-2 transition-all bg-background-dark/30 backdrop-blur-sm active:scale-95 group">
                                    <span class="material-symbols-outlined group-hover:text-primary transition-colors">play_circle</span> Xem Trailer
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (count($top_movies) > 1): ?>
            <div class="absolute bottom-24 md:bottom-28 left-0 right-0 z-30 flex justify-center gap-3">
                <?php foreach ($top_movies as $index => $movie): ?>
                    <button onclick="goToSlide(<?php echo $index; ?>)" class="dot h-2.5 rounded-full transition-all duration-300 shadow-md <?php echo $index === 0 ? 'bg-primary w-8' : 'bg-slate-400/50 w-2.5 hover:bg-slate-200'; ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </section>
        <?php else: ?>
        <div class="h-28"></div> <?php endif; ?>

        <section id="filter-section" class="max-w-4xl mx-auto w-full px-6 <?php echo (!$is_filtering && empty($view_all)) ? '-mt-16 relative z-20' : 'mt-4'; ?> mb-12">
            <div class="text-center mb-4 drop-shadow-lg">
                <?php if ($is_filtering): ?>
                    <h2 class="text-2xl font-bold">Kết quả lọc phim</h2>
                    <p class="text-primary text-sm font-medium mt-1">
                        <?php 
                            $filter_texts = [];
                            if($selected_cinema_name) $filter_texts[] = "Rạp: " . $selected_cinema_name;
                            if($filter_genre) $filter_texts[] = "Thể loại: " . $filter_genre;
                            if($search_query) $filter_texts[] = "Từ khóa: '" . $search_query . "'";
                            echo implode(' • ', $filter_texts);
                        ?>
                    </p>
                <?php elseif (isset($_SESSION["user_logged_in"])): ?>
                    <h2 class="text-2xl font-bold">Chào mừng trở lại, <span class="text-primary font-black"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name']))[count(explode(' ', trim($_SESSION['user_name']))) - 1]); ?>!</span></h2>
                    <p class="text-primary text-sm font-medium mt-1">Bạn muốn xem phim gì hôm nay?</p>
                <?php else: ?>
                    <h2 class="text-2xl font-bold">Khám phá thế giới điện ảnh</h2>
                <?php endif; ?>
            </div>

            <form method="GET" action="index.php#filter-section" class="bg-surface-dark border border-border-dark p-2 rounded-3xl md:rounded-full shadow-2xl flex flex-col md:flex-row items-center md:gap-2">
                <?php if(!empty($filter_cinema)): ?>
                    <input type="hidden" name="cinema_id" value="<?php echo $filter_cinema; ?>">
                <?php endif; ?>

                <div class="flex-1 w-full flex items-center px-4 pt-2 md:pt-0">
                    <span class="material-symbols-outlined text-slate-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off" placeholder="Tìm tên phim hoặc nội dung..." class="w-full bg-transparent border-none focus:ring-0 text-slate-200 placeholder:text-slate-500 py-3">
                </div>
                
                <div class="w-[90%] h-px md:w-px md:h-8 bg-border-dark my-1 md:my-0"></div>
                
                <div class="w-full md:w-auto px-4 md:px-2">
                    <select name="genre" class="w-full bg-transparent border-none focus:ring-0 text-slate-300 cursor-pointer py-3 pr-8 appearance-none">
                        <option class="bg-surface-dark text-slate-100" value="">Tất cả thể loại</option>
                        <?php foreach($all_genres as $g): ?>
                            <option class="bg-surface-dark text-slate-100" value="<?php echo $g; ?>" <?php echo ($filter_genre == $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex gap-2 w-full md:w-auto p-2 md:p-0">
                    <?php if ($is_filtering || !empty($view_all)): ?>
                        <a href="index.php" class="bg-red-500/10 text-red-500 font-bold px-4 py-3 rounded-full flex items-center justify-center transition-all hover:bg-red-500/20" title="Xóa bộ lọc">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="flex-1 md:flex-none bg-primary text-background-dark font-bold px-8 py-3 rounded-full flex items-center justify-center gap-2 hover:brightness-110 transition-all shadow-md">
                        <span class="material-symbols-outlined text-sm">tune</span> Lọc
                    </button>
                </div>
            </form>
        </section>

        <?php if ($view_all == '' || $view_all == 'now_showing'): ?>
        <section id="now-showing" class="max-w-7xl mx-auto w-full px-6 py-6">
            <div class="flex items-end justify-between mb-8 border-l-4 border-primary pl-4">
                <h2 class="text-3xl font-black uppercase tracking-tight">Phim đang chiếu</h2>
                
                <?php if (!$is_filtering && $view_all != 'now_showing' && count($now_showing) == 4): ?>
                    <a href="index.php?view_all=now_showing#now-showing" class="text-primary hover:text-white font-bold text-sm flex items-center gap-1 transition-colors">Xem tất cả <span class="material-symbols-outlined text-sm">chevron_right</span></a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 lg:gap-8">
                
            <!-- xem trailer phim đang chiếu -->
                <?php foreach ($now_showing as $movie): ?>
                <div class="group relative flex flex-col gap-3">
                    <div onclick="openTrailerModal('<?php echo htmlspecialchars($movie['trailer_url'] ?? ''); ?>')" class="relative w-full aspect-[2/3] rounded-xl overflow-hidden bg-surface-dark border border-border-dark shadow-lg cursor-pointer">
                        <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        
                        <div class="absolute top-2 right-2 bg-background-dark/80 backdrop-blur-sm border border-primary/30 text-primary text-xs font-bold px-2 py-1 rounded flex items-center gap-1 z-30 pointer-events-none">
                            <span class="material-symbols-outlined text-[12px]">star</span> 9.5
                        </div>
                        
                        <div class="absolute inset-0 bg-background-dark/80 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col items-center justify-center p-4 z-20 backdrop-blur-sm">
                            
                            <span class="material-symbols-outlined text-6xl text-slate-200 group-hover:text-primary transition-colors transform scale-90 group-hover:scale-110 duration-300 drop-shadow-[0_0_15px_rgba(242,204,13,0.5)]">play_circle</span>
                            
                            <a href="chon_suat.php?id=<?php echo $movie['id']; ?>" onclick="event.stopPropagation();" class="absolute bottom-6 w-[calc(100%-2rem)] text-center bg-primary text-background-dark px-6 py-3 rounded-full font-bold text-sm shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] hover:scale-105 transition-all transform translate-y-4 group-hover:translate-y-0">
                                Đặt Vé Ngay
                            </a>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-100 group-hover:text-primary transition-colors truncate" title="<?php echo htmlspecialchars($movie['title']); ?>"><?php echo htmlspecialchars($movie['title']); ?></h3>
                        <p class="text-sm text-slate-400 mt-1 truncate"><?php echo htmlspecialchars($movie['genre']); ?> • <?php echo $movie['duration']; ?>m</p>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($now_showing) == 0): ?>
                    <div class="col-span-2 md:col-span-4 text-center py-12 text-slate-500 bg-surface-dark rounded-xl border border-border-dark">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
                        <p>Không có phim ĐANG CHIẾU nào phù hợp.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($view_all == '' || $view_all == 'coming_soon'): ?>
        <section id="coming-soon" class="max-w-7xl mx-auto w-full px-6 py-12 mb-12">
            <div class="flex items-end justify-between mb-8 border-l-4 border-slate-500 pl-4">
                <h2 class="text-3xl font-black uppercase tracking-tight text-slate-300">Phim sắp chiếu</h2>
                
                <?php if (!$is_filtering && $view_all != 'coming_soon' && count($coming_soon) == 4): ?>
                    <a href="index.php?view_all=coming_soon#coming-soon" class="text-slate-400 hover:text-white font-bold text-sm flex items-center gap-1 transition-colors">Xem tất cả <span class="material-symbols-outlined text-sm">chevron_right</span></a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 lg:gap-8">
                <?php foreach ($coming_soon as $movie): ?>
                <div class="group relative flex flex-col gap-3 opacity-90 hover:opacity-100 transition-opacity">
                    <div class="relative w-full aspect-[2/3] rounded-xl overflow-hidden bg-surface-dark border border-border-dark shadow-lg">
                        <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 filter grayscale-[30%] group-hover:grayscale-0">
                        <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black to-transparent p-4 pt-12">
                            <span class="bg-surface-dark/90 backdrop-blur-sm border border-slate-600 text-slate-200 text-xs font-bold px-3 py-1.5 rounded uppercase block w-fit text-center">
                                Khởi chiếu: <?php echo date('d/m', strtotime($movie['release_date'])); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-300 group-hover:text-slate-100 transition-colors truncate" title="<?php echo htmlspecialchars($movie['title']); ?>"><?php echo htmlspecialchars($movie['title']); ?></h3>
                        <p class="text-sm text-slate-500 mt-1 truncate"><?php echo htmlspecialchars($movie['genre']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (count($coming_soon) == 0): ?>
                    <div class="col-span-2 md:col-span-4 text-center py-12 text-slate-500 bg-surface-dark rounded-xl border border-border-dark">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
                        <p>Không có phim SẮP CHIẾU nào phù hợp.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- khối HTML của Popup trailer -->
    <div id="trailerModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/95 backdrop-blur-md opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 cursor-pointer" onclick="closeTrailerModal()"></div> 
        
        <div class="relative w-full max-w-5xl mx-auto px-4 sm:px-6 z-10 scale-95 transition-transform duration-300" id="trailerContainer">
            <button onclick="closeTrailerModal()" class="absolute -top-12 right-4 sm:right-6 text-slate-400 hover:text-primary transition-colors flex items-center gap-1 font-bold tracking-widest uppercase text-sm">
                Đóng <span class="material-symbols-outlined text-2xl">close</span>
            </button>
            
            <div class="relative pt-[56.25%] w-full rounded-2xl overflow-hidden shadow-[0_0_50px_rgba(242,204,13,0.15)] border border-accent-dark bg-black">
                <iframe id="trailerIframe" class="absolute inset-0 w-full h-full" src="" frameborder="0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
            </div>
        </div>
    </div>

    <!-- Javascript xử lý logic -->
    <script>
        function openTrailerModal(youtubeUrl) {
            if (!youtubeUrl) {
                alert('Bộ phim này đang được cập nhật Trailer!');
                return;
            }
            
            // Trích xuất ID video từ nhiều định dạng link Youtube khác nhau
            let videoId = '';
            if (youtubeUrl.includes('watch?v=')) {
                videoId = youtubeUrl.split('watch?v=')[1].split('&')[0];
            } else if (youtubeUrl.includes('youtu.be/')) {
                videoId = youtubeUrl.split('youtu.be/')[1].split('?')[0];
            } else if (youtubeUrl.includes('embed/')) {
                videoId = youtubeUrl.split('embed/')[1].split('?')[0];
            }

            // Tạo link Embed ép tự động phát
            let embedUrl = videoId ? `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&showinfo=0&modestbranding=1` : youtubeUrl;

            // Lấy các phần tử DOM
            const modal = document.getElementById('trailerModal');
            const container = document.getElementById('trailerContainer');
            const iframe = document.getElementById('trailerIframe');
            
            // Gắn link vào iframe
            iframe.src = embedUrl;
            
            // Hiển thị Popup với hiệu ứng Fade-in & Scale-up
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                container.classList.remove('scale-95');
                container.classList.add('scale-100');
            }, 10);
        }

        function closeTrailerModal() {
            const modal = document.getElementById('trailerModal');
            const container = document.getElementById('trailerContainer');
            const iframe = document.getElementById('trailerIframe');
            
            // Ẩn Popup với hiệu ứng Fade-out & Scale-down
            modal.classList.add('opacity-0');
            container.classList.remove('scale-100');
            container.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                iframe.src = ''; // CỰC KỲ QUAN TRỌNG: Xóa src để dừng tiếng video
            }, 300);
        }

        // LOGIC CHẠY SLIDER BANNER TỰ ĐỘNG
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.dot');
        const totalSlides = slides.length;
        let slideInterval;

        function updateSlider(index) {
            if (totalSlides === 0) return;
            
            // Xóa trạng thái active của tất cả slide và dot
            slides.forEach(slide => {
                slide.classList.remove('opacity-100', 'z-10', 'pointer-events-auto');
                slide.classList.add('opacity-0', 'z-0', 'pointer-events-none');
            });
            dots.forEach(dot => {
                dot.classList.remove('bg-primary', 'w-8');
                dot.classList.add('bg-slate-500/50', 'w-2.5');
            });

            // Gắn trạng thái active cho slide hiện tại (Hiệu ứng Fade-in mượt mà)
            slides[index].classList.remove('opacity-0', 'z-0', 'pointer-events-none');
            slides[index].classList.add('opacity-100', 'z-10', 'pointer-events-auto');
            
            if(dots[index]) {
                dots[index].classList.remove('bg-slate-500/50', 'w-2.5');
                dots[index].classList.add('bg-primary', 'w-8');
            }
            
            currentSlide = index;
        }

        function nextSlide() {
            let next = (currentSlide + 1) % totalSlides;
            updateSlider(next);
        }

        // Chuyển slide khi người dùng bấm vào dấu chấm
        function goToSlide(index) {
            updateSlider(index);
            resetInterval(); // Reset lại thời gian để không bị chuyển quá nhanh
        }

        // Tự động chuyển slide mỗi 5 giây
        function resetInterval() {
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 5000); 
        }

        if (totalSlides > 1) {
            resetInterval();
        }
    </script>

</body>
</html>