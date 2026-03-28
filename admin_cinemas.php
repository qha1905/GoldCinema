<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// 1. XỬ LÝ XÓA RẠP
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM cinemas WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: admin_cinemas.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Không thể xóa rạp này vì đang có lịch chiếu/vé liên quan.";
    }
}

// 2. XỬ LÝ TÌM KIẾM
$search_query = $_GET['search'] ?? '';
$where_sql = "1=1";
$params = [];

if (!empty($search_query)) {
    $where_sql .= " AND (name LIKE :search OR address LIKE :search)";
    $params[':search'] = "%" . $search_query . "%";
}

// Thống kê rạp
$total_cinemas = $pdo->query("SELECT COUNT(*) FROM cinemas")->fetchColumn();
$total_rooms = $pdo->query("SELECT SUM(total_rooms) FROM cinemas")->fetchColumn() ?: 0;
$active_cinemas = $pdo->query("SELECT COUNT(*) FROM cinemas WHERE status = 'active'")->fetchColumn();

// Lấy danh sách rạp
$stmt = $pdo->prepare("SELECT * FROM cinemas WHERE $where_sql ORDER BY id DESC");
$stmt->execute($params);
$cinemas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Hệ thống Rạp - CineAdmin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; } 
        .custom-scrollbar::-webkit-scrollbar { width: 6px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
        /* Fix Autofill background */
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #2a2614 inset !important;
            -webkit-text-fill-color: #e2e8f0 !important;
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
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-slate-100 leading-none"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-[10px] text-primary font-medium uppercase mt-1">Quản trị viên</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-accent-dark border-2 border-primary/30 flex items-center justify-center text-primary font-bold">
                    <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="bg-green-500/10 border border-green-500/50 text-green-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">check_circle</span> Rạp đã được xóa thành công!
                </div>
            <?php endif; ?>

            <?php if(isset($error_msg)): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">error</span> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-black text-slate-100 tracking-tight uppercase">Danh sách cụm rạp</h2>
                    <p class="text-slate-400 mt-1">Quản lý các địa điểm rạp chiếu và trạng thái vận hành trên toàn hệ thống.</p>
                </div>
                <a href="add_cinema.php" class="bg-primary hover:bg-primary/90 text-background-dark px-6 py-3 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-primary/20 transition-all active:scale-95">
                    <span class="material-symbols-outlined">add_circle</span> Thêm rạp mới
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 opacity-5 text-slate-100 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-8xl">theater_comedy</span>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Tổng số cụm rạp</p>
                    <h3 class="text-4xl font-black text-primary mt-2"><?php echo $total_cinemas; ?></h3>
                </div>
                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 opacity-5 text-slate-100 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-8xl">meeting_room</span>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Tổng số phòng chiếu</p>
                    <h3 class="text-4xl font-black text-slate-100 mt-2"><?php echo $total_rooms; ?></h3>
                </div>
                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 opacity-5 text-slate-100 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-8xl">check_circle</span>
                    </div>
                    <p class="text-slate-400 text-sm font-medium">Đang hoạt động</p>
                    <h3 class="text-4xl font-black text-green-500 mt-2"><?php echo $active_cinemas; ?></h3>
                </div>
            </div>

            <form method="GET" action="admin_cinemas.php" class="bg-surface-dark border border-accent-dark p-4 rounded-2xl mb-8 flex flex-col md:flex-row gap-4 shadow-lg">
                <div class="flex-1 relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off" placeholder="Tìm tên rạp, địa chỉ..." class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 pl-10 pr-4 text-slate-200 focus:bg-accent-dark/30 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                </div>
                <div class="flex gap-4">
                    <button type="submit" class="bg-accent-dark hover:bg-primary hover:text-background-dark text-slate-200 font-bold px-6 py-3 rounded-xl transition-all">
                        Tìm kiếm
                    </button>
                    <?php if(!empty($_GET['search'])): ?>
                        <a href="admin_cinemas.php" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 font-bold px-4 py-3 rounded-xl transition-all flex items-center justify-center" title="Xóa tìm kiếm">
                            <span class="material-symbols-outlined">close</span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="bg-surface-dark border border-accent-dark rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full border-collapse text-left min-w-[800px]">
                        <thead>
                            <tr class="bg-accent-dark/30 text-slate-400 text-xs font-bold uppercase tracking-widest border-b border-accent-dark">
                                <th class="px-6 py-4">Tên cụm rạp</th>
                                <th class="px-6 py-4">Địa chỉ</th>
                                <th class="px-6 py-4 text-center">Phòng chiếu</th>
                                <th class="px-6 py-4">Trạng thái</th>
                                <th class="px-6 py-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-dark/50">
                            <?php foreach ($cinemas as $cinema): ?>
                            <tr class="hover:bg-accent-dark/20 transition-colors group">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 bg-primary/10 border border-primary/20 text-primary rounded-lg flex items-center justify-center font-bold">
                                            <?php echo strtoupper(mb_substr($cinema['name'], 0, 2, 'UTF-8')); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-100 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($cinema['name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($cinema['type']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-sm text-slate-300 max-w-xs truncate" title="<?php echo htmlspecialchars($cinema['address']); ?>">
                                    <?php echo htmlspecialchars($cinema['address']); ?>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="px-3 py-1 bg-background-dark rounded-full text-slate-300 font-bold text-sm"><?php echo $cinema['total_rooms']; ?></span>
                                </td>
                                <td class="px-6 py-5">
                                    <?php if ($cinema['status'] == 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-green-500/10 text-green-500 border border-green-500/20">Đang hoạt động</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-amber-500/10 text-amber-500 border border-amber-500/20">Bảo trì</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-5 text-right whitespace-nowrap">
                                    <a href="edit_cinema.php?id=<?php echo $cinema['id']; ?>" class="inline-block p-2 rounded-lg text-slate-400 hover:bg-primary/20 hover:text-primary transition-all mr-2" title="Sửa">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                    </a>
                                    <a href="admin_cinemas.php?action=delete&id=<?php echo $cinema['id']; ?>" onclick="return confirm('Xác nhận xóa rạp chiếu này?');" class="inline-block p-2 rounded-lg text-slate-400 hover:bg-red-500/10 hover:text-red-500 transition-all" title="Xóa">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (count($cinemas) === 0): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">apartment</span><br>
                                    Không tìm thấy dữ liệu rạp chiếu.
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