<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

$cinema_id = $_GET['id'] ?? ($_POST['id'] ?? 0);
if (!$cinema_id) {
    header("Location: admin_cinemas.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $address = trim($_POST['address']);
    $total_rooms = (int)$_POST['total_rooms'];
    $status = $_POST['status'];

    if (empty($name) || empty($address)) {
        $error = "Vui lòng nhập đầy đủ Tên rạp và Địa chỉ.";
    } else {
        try {
            $sql = "UPDATE cinemas SET name = :name, type = :type, address = :address, total_rooms = :total_rooms, status = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':address' => $address,
                ':total_rooms' => $total_rooms,
                ':status' => $status,
                ':id' => $cinema_id
            ]);
            $message = "Cập nhật thông tin rạp thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi Database: " . $e->getMessage();
        }
    }
}

// Lấy dữ liệu cũ
$stmt = $pdo->prepare("SELECT * FROM cinemas WHERE id = :id");
$stmt->execute(['id' => $cinema_id]);
$cinema = $stmt->fetch();

if (!$cinema) {
    header("Location: admin_cinemas.php");
    exit;
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sửa thông tin rạp - Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; }</style>
</head>
<body class="bg-background-dark text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4">
                <a href="admin_cinemas.php" class="flex items-center gap-2 text-slate-400 hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span class="font-bold text-sm">Quay lại danh sách</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border-2 border-primary/30 flex items-center justify-center bg-accent-dark text-primary font-bold">
                    <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-3xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl font-black text-slate-100 mb-2 tracking-tight uppercase">Chỉnh sửa rạp chiếu</h1>
                    <p class="text-slate-400">Cập nhật thông tin cho chi nhánh <strong class="text-primary"><?php echo htmlspecialchars($cinema['name']); ?></strong>.</p>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="bg-green-500/10 text-green-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">check_circle</span> <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <?php if(!empty($error)): ?>
                    <div class="bg-red-500/10 text-red-500 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">error</span> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-8 shadow-2xl">
                    <form method="POST" action="edit_cinema.php?id=<?php echo $cinema_id; ?>" class="flex flex-col gap-6">
                        
                        <input type="hidden" name="id" value="<?php echo $cinema_id; ?>">

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-300">Tên cụm rạp</label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($cinema['name']); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-300">Địa chỉ chi tiết</label>
                            <input type="text" name="address" required value="<?php echo htmlspecialchars($cinema['address']); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Phân khúc</label>
                                <select name="type" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="Tiêu chuẩn" <?php echo ($cinema['type'] == 'Tiêu chuẩn') ? 'selected' : ''; ?>>Tiêu chuẩn</option>
                                    <option class="bg-surface-dark" value="Phân khúc cao cấp" <?php echo ($cinema['type'] == 'Phân khúc cao cấp') ? 'selected' : ''; ?>>Cao cấp (Gold Class/IMAX)</option>
                                </select>
                            </div>
                            
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Số phòng chiếu</label>
                                <select name="total_rooms" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark text-slate-400" value="">-- Chọn số phòng --</option>
                                    <option class="bg-surface-dark" value="1" <?php echo ($cinema['total_rooms'] == 1) ? 'selected' : ''; ?>>1 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="2" <?php echo ($cinema['total_rooms'] == 2) ? 'selected' : ''; ?>>2 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="3" <?php echo ($cinema['total_rooms'] == 3) ? 'selected' : ''; ?>>3 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="4" <?php echo ($cinema['total_rooms'] == 4) ? 'selected' : ''; ?>>4 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="5" <?php echo ($cinema['total_rooms'] == 5) ? 'selected' : ''; ?>>5 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="6" <?php echo ($cinema['total_rooms'] == 6) ? 'selected' : ''; ?>>6 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="7" <?php echo ($cinema['total_rooms'] == 7) ? 'selected' : ''; ?>>7 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="8" <?php echo ($cinema['total_rooms'] == 8) ? 'selected' : ''; ?>>8 Phòng chiếu</option>
                                    <option class="bg-surface-dark text-primary font-bold" value="10" <?php echo ($cinema['total_rooms'] == 10) ? 'selected' : ''; ?>>10 Phòng (Quy mô lớn)</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Trạng thái</label>
                                <select name="status" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="active" <?php echo ($cinema['status'] == 'active') ? 'selected' : ''; ?>>Đang hoạt động</option>
                                    <option class="bg-surface-dark" value="maintenance" <?php echo ($cinema['status'] == 'maintenance') ? 'selected' : ''; ?>>Bảo trì</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-accent-dark pt-6 mt-4 flex justify-end gap-4">
                            <button type="submit" class="bg-primary text-background-dark font-bold px-8 py-3 rounded-xl shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] hover:bg-primary/90 transition-all active:scale-95 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Cập nhật rạp
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>