<?php
session_start();
require_once 'includes/db_connect.php';

// Bảo mật: Kiểm tra Admin
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. XỬ LÝ XÓA PHIM
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        // Lưu ý: Nếu phim đã có người đặt vé (nằm trong bảng orders) thì không nên xóa cứng,
        // hoặc phải xóa đơn hàng trước. Ở đây dùng lệnh xóa cơ bản.
        $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: admin_movies.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Không thể xóa phim này vì đã có dữ liệu liên quan (lịch chiếu/vé).";
    }
}

// ==========================================
// 2. XỬ LÝ TÌM KIẾM, LỌC VÀ SẮP XẾP
// ==========================================
$where_clauses = ["1=1"]; // Điều kiện mặc định luôn đúng
$params = [];

// Tìm kiếm theo tên hoặc thể loại
$search_query = $_GET['search'] ?? '';
if (!empty($search_query)) {
    $where_clauses[] = "(title LIKE :search OR genre LIKE :search)";
    $params[':search'] = "%" . $search_query . "%";
}

// Lọc theo trạng thái
$filter_status = $_GET['status'] ?? '';
// Bổ sung thêm 'stopped' vào mảng dưới đây:
if (in_array($filter_status, ['now_showing', 'coming_soon', 'stopped'])) {
    $where_clauses[] = "status = :status";
    $params[':status'] = $filter_status;
}

// Sắp xếp
$sort_option = $_GET['sort'] ?? 'newest';
$order_by = "id DESC"; // Mặc định
if ($sort_option == 'title_asc') $order_by = "title ASC";
if ($sort_option == 'title_desc') $order_by = "title DESC";
if ($sort_option == 'date_asc') $order_by = "release_date ASC";
if ($sort_option == 'date_desc') $order_by = "release_date DESC";

// Ghép câu lệnh SQL
$sql = "SELECT * FROM movies WHERE " . implode(" AND ", $where_clauses) . " ORDER BY " . $order_by;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_movies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Quản lý phim - Admin Cinema</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e",
                    },
                    fontFamily: {"display": ["Be Vietnam Pro"]}
                },
            },
        }
    </script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; } .custom-scrollbar::-webkit-scrollbar { width: 6px; } .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
        /* Sửa lỗi nền trắng khi Autofill của trình duyệt */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #2a2614 inset !important; /* Trùng với màu bg-surface-dark */
            -webkit-text-fill-color: #e2e8f0 !important; /* Chữ màu sáng */
            transition: background-color 5000s ease-in-out 0s;
        }
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
                    <div class="w-10 h-10 rounded-full border-2 border-primary/30 p-0.5">
                        <div class="w-full h-full rounded-full bg-accent-dark flex items-center justify-center text-primary font-bold">
                            <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="bg-green-500/10 border border-green-500/50 text-green-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">check_circle</span> Phim đã được xóa thành công!
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_msg)): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">error</span> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-slate-100 mb-2 tracking-tight uppercase">Danh sách phim</h1>
                    <p class="text-slate-400">Quản lý kho phim, tìm kiếm và phân loại dữ liệu.</p>
                </div>
                <a href="add_movie.php" class="bg-primary hover:bg-primary/90 text-background-dark px-6 py-3 rounded-xl font-bold flex items-center gap-2 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] active:scale-95 whitespace-nowrap">
                    <span class="material-symbols-outlined font-bold">add</span> Thêm phim mới
                </a>
            </div>

            <form method="GET" action="admin_movies.php" class="bg-surface-dark border border-accent-dark p-4 rounded-2xl mb-8 flex flex-col md:flex-row gap-4 shadow-lg">
                <div class="flex-1 relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Tìm tên phim, thể loại..." autocomplete="off" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 pl-10 pr-4 text-slate-200 focus:bg-accent-dark/30 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                </div>
                
                <div class="flex gap-4">
                    <select name="status" class="bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-200 focus:border-primary outline-none cursor-pointer min-w-[180px]">
                        <option class="bg-surface-dark text-slate-100" value="">Tất cả trạng thái</option>
                        <option class="bg-surface-dark text-slate-100" value="now_showing" <?php echo $filter_status == 'now_showing' ? 'selected' : ''; ?>>Đang chiếu</option>
                        <option class="bg-surface-dark text-slate-100" value="coming_soon" <?php echo $filter_status == 'coming_soon' ? 'selected' : ''; ?>>Sắp chiếu</option>
                        <option class="bg-surface-dark text-slate-100" value="stopped" <?php echo $filter_status == 'stopped' ? 'selected' : ''; ?>>Ngừng chiếu</option>
                    </select>

                    <select name="sort" class="bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-200 focus:border-primary outline-none cursor-pointer min-w-[240px]">
                        <option class="bg-surface-dark text-slate-100" value="newest" <?php echo $sort_option == 'newest' ? 'selected' : ''; ?>>Mới thêm nhất</option>
                        <option class="bg-surface-dark text-slate-100" value="title_asc" <?php echo $sort_option == 'title_asc' ? 'selected' : ''; ?>>Tên: A - Z</option>
                        <option class="bg-surface-dark text-slate-100" value="title_desc" <?php echo $sort_option == 'title_desc' ? 'selected' : ''; ?>>Tên: Z - A</option>
                        <option class="bg-surface-dark text-slate-100" value="date_desc" <?php echo $sort_option == 'date_desc' ? 'selected' : ''; ?>>Ngày chiếu: Mới - Cũ</option>
                        <option class="bg-surface-dark text-slate-100" value="date_asc" <?php echo $sort_option == 'date_asc' ? 'selected' : ''; ?>>Ngày chiếu: Cũ - Mới</option>
                    </select>

                    <button type="submit" class="bg-accent-dark hover:bg-primary hover:text-background-dark text-slate-200 font-bold px-6 py-3 rounded-xl transition-all">
                        Áp dụng
                    </button>
                    
                    <?php if(!empty($_GET)): ?>
                        <a href="admin_movies.php" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 font-bold px-4 py-3 rounded-xl transition-all flex items-center justify-center" title="Xóa bộ lọc">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="bg-surface-dark rounded-2xl border border-accent-dark overflow-hidden shadow-2xl">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-accent-dark/30 border-b border-accent-dark text-xs font-bold uppercase tracking-widest text-slate-400">
                                <th class="px-6 py-4">Tên phim</th>
                                <th class="px-6 py-4">Thể loại</th>
                                <th class="px-6 py-4 text-center">Thời lượng</th>
                                <th class="px-6 py-4">Khởi chiếu</th>
                                <th class="px-6 py-4">Trạng thái</th>
                                <th class="px-6 py-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-dark/50">
                            
                            <?php foreach ($all_movies as $movie): ?>
                            <tr class="hover:bg-accent-dark/20 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-16 rounded-md bg-slate-800 flex-shrink-0 overflow-hidden border border-accent-dark shadow-lg">
                                            <div class="w-full h-full bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($movie['poster_url']); ?>');"></div>
                                        </div>
                                        <div class="max-w-[200px]">
                                            <p class="font-bold text-slate-200 group-hover:text-primary transition-colors truncate" title="<?php echo htmlspecialchars($movie['title']); ?>"><?php echo htmlspecialchars($movie['title']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-400 max-w-[150px] truncate"><?php echo htmlspecialchars($movie['genre']); ?></td>
                                <td class="px-6 py-4 text-sm text-slate-400 text-center"><?php echo htmlspecialchars($movie['duration']); ?>'</td>
                                <td class="px-6 py-4 text-sm text-slate-400">
                                    <?php echo date('d/m/Y', strtotime($movie['release_date'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($movie['status'] == 'now_showing'): ?>
                                        <span class="px-3 py-1 rounded-full bg-green-500/10 text-green-500 text-[10px] font-bold uppercase tracking-widest">Đang chiếu</span>
                                    <?php elseif ($movie['status'] == 'coming_soon'): ?>
                                        <span class="px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest">Sắp chiếu</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full bg-slate-500/10 text-slate-400 text-[10px] font-bold uppercase tracking-widest">Ngừng chiếu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="edit_movie.php?id=<?php echo $movie['id']; ?>" class="inline-block p-2 rounded-lg text-slate-400 hover:bg-primary/20 hover:text-primary transition-all mr-2" title="Sửa">
                                        <span class="material-symbols-outlined text-xl">edit</span>
                                    </a>
                                    <a href="admin_movies.php?action=delete&id=<?php echo $movie['id']; ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa phim này? Hành động này không thể hoàn tác.');" class="inline-block p-2 rounded-lg text-slate-400 hover:bg-red-500/10 hover:text-red-500 transition-all" title="Xóa">
                                        <span class="material-symbols-outlined text-xl">delete</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($all_movies) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span><br>
                                    Không tìm thấy dữ liệu phù hợp với bộ lọc của bạn.
                                </td>
                            </tr>
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