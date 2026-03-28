<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
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

    if (empty($name) || empty($address) || empty($total_rooms)) {
        $error = "Vui lòng nhập đầy đủ Tên rạp, Địa chỉ và Số phòng chiếu.";
    } else {
        try {
            $sql = "INSERT INTO cinemas (name, type, address, total_rooms, status) 
                    VALUES (:name, :type, :address, :total_rooms, :status)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':address' => $address,
                ':total_rooms' => $total_rooms,
                ':status' => $status
            ]);
            $message = "Thêm rạp chiếu mới thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi Database: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Thêm rạp mới - Admin</title>
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
                    <h1 class="text-3xl font-black text-slate-100 mb-2 tracking-tight uppercase">Thêm cụm rạp mới</h1>
                    <p class="text-slate-400">Điền thông tin để mở rộng hệ thống chi nhánh rạp chiếu.</p>
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
                    <form method="POST" action="add_cinema.php" class="flex flex-col gap-6">
                        
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-300">Tên cụm rạp</label>
                            <input type="text" name="name" required placeholder="Ví dụ: CGV Vincom Đồng Khởi" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-bold text-slate-300">Địa chỉ chi tiết</label>
                            <input type="text" name="address" required placeholder="Số nhà, Đường, Quận/Huyện, Thành phố" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Phân khúc</label>
                                <select name="type" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="Tiêu chuẩn">Tiêu chuẩn</option>
                                    <option class="bg-surface-dark" value="Phân khúc cao cấp">Cao cấp (Gold Class/IMAX)</option>
                                </select>
                            </div>
                            
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Số phòng chiếu</label>
                                <select name="total_rooms" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark text-slate-400" value="">-- Chọn số phòng --</option>
                                    <option class="bg-surface-dark" value="1">1 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="2">2 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="3">3 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="4">4 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="5">5 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="6">6 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="7">7 Phòng chiếu</option>
                                    <option class="bg-surface-dark" value="8">8 Phòng chiếu</option>
                                    <option class="bg-surface-dark text-primary font-bold" value="10">10 Phòng (Quy mô lớn)</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Trạng thái</label>
                                <select name="status" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="active">Đang hoạt động</option>
                                    <option class="bg-surface-dark" value="maintenance">Bảo trì</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-accent-dark pt-6 mt-4 flex justify-end gap-4">
                            <button type="submit" class="bg-primary text-background-dark font-bold px-8 py-3 rounded-xl shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] hover:bg-primary/90 transition-all active:scale-95 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Lưu rạp mới
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