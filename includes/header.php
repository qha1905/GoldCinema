<?php
// 1. Lấy danh sách rạp cho menu dropdown
if (!isset($nav_cinemas)) {
    $stmt_cinemas = $pdo->query("SELECT id, name FROM cinemas WHERE status = 'active' ORDER BY name ASC");
    $nav_cinemas = $stmt_cinemas->fetchAll();
}

// 2. TỰ ĐỘNG QUÉT VÀ LỌC THỂ LOẠI PHIM TỪ DATABASE
if (!isset($nav_genres)) {
    $stmt_genres = $pdo->query("SELECT genre FROM movies");
    $raw_genres = $stmt_genres->fetchAll(PDO::FETCH_COLUMN);
    
    $unique_genres = [];
    foreach ($raw_genres as $g_string) {
        if (empty(trim($g_string))) continue;
        $parts = explode(',', $g_string);
        foreach ($parts as $part) {
            $part = trim($part);
            if (!empty($part)) {
                $normalized = mb_convert_case($part, MB_CASE_TITLE, "UTF-8");
                $unique_genres[$normalized] = $normalized;
            }
        }
    }
    ksort($unique_genres); 
    $nav_genres = $unique_genres;
}

$current_search = $_GET['search'] ?? '';
$current_genre = $_GET['genre'] ?? '';
$current_view = $_GET['view'] ?? '';
?>
<header class="flex items-center justify-between border-b border-border-dark px-6 py-3 md:px-10 lg:px-20 bg-background-dark/80 backdrop-blur-md sticky top-0 z-50 gap-4">
    
    <div class="flex-1 xl:flex-none">
        <a href="index.php" class="inline-flex items-center gap-3 hover:scale-105 transition-transform shrink-0">
            <img src="images/my_logo.png" alt="Logo H Cinema" class="h-12 w-12 object-cover rounded-full shadow-md border border-primary/30">
            <h1 class="text-2xl font-black tracking-tight text-primary uppercase hidden sm:block">H Cinema</h1>
        </a>
    </div>

    <nav class="hidden xl:flex flex-1 justify-center items-center gap-6 2xl:gap-10">
        <a class="text-sm font-bold text-slate-100 hover:text-primary transition-colors uppercase tracking-widest" href="index.php">Trang chủ</a>
        
        <div class="relative group cursor-pointer">
            <div class="flex items-center gap-1 text-sm font-bold text-slate-300 hover:text-primary transition-colors uppercase tracking-widest">Rạp <span class="material-symbols-outlined text-sm">expand_more</span></div>
            <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 w-56 bg-surface-dark border border-border-dark rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
                <div class="p-2 space-y-1">
                    <?php foreach($nav_cinemas as $c): ?>
                    <a href="index.php?cinema_id=<?php echo $c['id']; ?>" class="block px-4 py-2.5 text-sm text-slate-300 hover:bg-primary hover:text-background-dark rounded-lg transition-all font-medium"><?php echo htmlspecialchars($c['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <a class="text-sm font-bold <?php echo ($current_view == 'now-showing') ? 'text-primary' : 'text-slate-300'; ?> hover:text-primary transition-colors uppercase tracking-widest" href="#now-showing">Phim đang chiếu</a>
        <a class="text-sm font-bold <?php echo ($current_view == 'coming-soon') ? 'text-primary' : 'text-slate-300'; ?> hover:text-primary transition-colors uppercase tracking-widest" href="#coming-soon">Phim sắp chiếu</a>
    </nav>

    <div class="flex items-center justify-end gap-4 xl:flex-1 shrink-0 w-full xl:w-auto">
        
        <div class="hidden lg:block w-full max-w-[380px] 2xl:max-w-[450px]">
            <form action="index.php" method="GET" class="w-full flex items-center bg-surface-dark border border-border-dark rounded-full overflow-hidden focus-within:border-primary focus-within:shadow-[0_0_15px_rgba(242,204,13,0.15)] transition-all">
                <div class="flex items-center pl-3 text-slate-500"><span class="material-symbols-outlined text-lg">search</span></div>
                
                <input type="text" name="search" value="<?php echo htmlspecialchars($current_search); ?>" placeholder="Tìm tên phim..." class="w-full bg-transparent border-none text-sm text-slate-200 px-3 py-2.5 focus:ring-0 placeholder:text-slate-600 outline-none">
                
                <div class="h-5 w-px bg-border-dark"></div>
                
                <select name="genre" class="bg-transparent border-none text-xs text-slate-300 py-2 pl-2 pr-6 focus:ring-0 cursor-pointer font-medium outline-none appearance-none max-w-[110px] truncate">
                    <option value="" class="bg-background-dark">Thể loại</option>
                    <?php foreach($nav_genres as $g): ?>
                        <option value="<?php echo htmlspecialchars($g); ?>" class="bg-background-dark" <?php echo (mb_strtolower($current_genre, 'UTF-8') == mb_strtolower($g, 'UTF-8')) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="bg-primary text-background-dark px-4 py-2.5 text-xs font-black uppercase tracking-widest hover:bg-primary/90 transition-colors shrink-0">Tìm</button>
            </form>
        </div>

        <div class="flex items-center lg:border-l lg:border-border-dark lg:pl-4 shrink-0">
            <?php if (isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"] === true): ?>
                <div class="relative group cursor-pointer">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <p class="text-[10px] font-black text-primary uppercase leading-none">Thành viên</p>
                            <p class="text-sm font-bold text-slate-100"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name']))[count(explode(' ', trim($_SESSION['user_name']))) - 1]); ?></p>
                        </div>
                        <div class="size-10 rounded-full border-2 border-primary/30 p-0.5">
                            <div class="w-full h-full rounded-full bg-surface-dark flex items-center justify-center text-primary font-bold"><?php echo substr(trim($_SESSION['user_name']), 0, 1); ?></div>
                        </div>
                    </div>
                    <div class="absolute top-full right-0 mt-3 w-48 bg-surface-dark border border-border-dark rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                        <div class="p-2 space-y-1">
                            <a href="tai_khoan.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-accent-dark rounded-lg"><span class="material-symbols-outlined text-sm">person</span> Tài khoản</a>
                            <a href="ve_cua_toi.php" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-300 hover:bg-accent-dark rounded-lg"><span class="material-symbols-outlined text-sm">confirmation_number</span> Vé của tôi</a>
                            <hr class="border-border-dark my-1">
                            <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 rounded-lg"><span class="material-symbols-outlined text-sm">logout</span> Đăng xuất</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="bg-primary text-background-dark px-5 py-2.5 rounded-full font-bold text-sm hover:bg-primary/90 transition-all shadow-lg shadow-primary/10 whitespace-nowrap">ĐĂNG NHẬP</a>
            <?php endif; ?>
        </div>

    </div>
</header>