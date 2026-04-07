<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = '';

// =======================================================
// XỬ LÝ THÊM BẮP NƯỚC (UPLOAD ẢNH MỚI)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (int)$_POST['price'];
    
    $image_url = ''; 

    if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] == 0) {
        $upload_dir = 'uploads/concessions/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = $_FILES['image_upload']['name'];
        $file_tmp = $_FILES['image_upload']['tmp_name'];
        
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = uniqid('fb_') . '.' . $file_ext;
            $target_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_path)) {
                $image_url = $target_path; 
            } else {
                $msg = "<div class='bg-red-500/10 text-red-500 p-4 rounded-xl mb-6 font-bold border border-red-500/20'>Lỗi hệ thống khi lưu file ảnh!</div>";
            }
        } else {
            $msg = "<div class='bg-red-500/10 text-red-500 p-4 rounded-xl mb-6 font-bold border border-red-500/20'>Định dạng ảnh không hợp lệ (Chỉ nhận JPG, PNG, GIF, WEBP).</div>";
        }
    } else {
        $msg = "<div class='bg-red-500/10 text-red-500 p-4 rounded-xl mb-6 font-bold border border-red-500/20'>Vui lòng chọn một hình ảnh!</div>";
    }

    if ($image_url !== '') {
        $stmt = $pdo->prepare("INSERT INTO concessions (name, description, price, image_url, status) VALUES (?, ?, ?, ?, 'active')");
        if ($stmt->execute([$name, $description, $price, $image_url])) {
            $msg = "<div class='bg-green-500/10 text-green-500 p-4 rounded-xl mb-6 font-bold border border-green-500/20'>Thêm món mới thành công!</div>";
        }
    }
}

// =======================================================
// XỬ LÝ XÓA BẮP NƯỚC (XÓA DATA + XÓA FILE ẢNH VẬT LÝ)
// =======================================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT image_url FROM concessions WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if ($item && !empty($item['image_url'])) {
        if (strpos($item['image_url'], 'http') !== 0 && file_exists($item['image_url'])) {
            unlink($item['image_url']);
        }
    }

    $pdo->prepare("DELETE FROM concessions WHERE id = ?")->execute([$id]);
    header("Location: admin_concessions.php?msg=deleted");
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $msg = "<div class='bg-green-500/10 text-green-500 p-4 rounded-xl mb-6 font-bold border border-green-500/20'>Đã xóa món và dọn dẹp file ảnh thành công!</div>";
}

$items = $pdo->query("SELECT * FROM concessions ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Quản lý Bắp Nước - CineAdmin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"primary":"#f2cc0d","background-dark":"#1a180b","surface-dark":"#2a2614","accent-dark":"#403a1e"}}}}</script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180b; } .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }</style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col">
<div class="flex h-screen overflow-hidden">
    <?php require_once 'includes/admin_sidebar.php'; ?>
    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4 flex-1"></div>
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
            <?php echo $msg; ?>
            <div class="flex flex-col mb-8">
                <h1 class="text-3xl font-black text-slate-100 uppercase tracking-tight">Quản lý Bắp Nước</h1>
                <p class="text-slate-400 mt-1">Thêm các Combo bắp nước để tăng doanh thu cho hệ thống rạp.</p>
            </div>

            <form method="POST" enctype="multipart/form-data" class="bg-surface-dark border border-accent-dark rounded-2xl p-6 mb-8 shadow-xl">
                <h3 class="text-lg font-bold text-primary mb-4 border-b border-accent-dark pb-2">Thêm món mới</h3>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Tên Combo / Món</label>
                        <input type="text" name="name" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Giá bán (VNĐ)</label>
                        <input type="number" name="price" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                    </div>
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Mô tả ngắn</label>
                        <input type="text" name="description" placeholder="VD: 1 Bắp + 2 Nước" class="w-full bg-background-dark border border-accent-dark rounded-xl py-2 px-3 text-slate-200 outline-none focus:border-primary">
                    </div>
                    <div class="md:col-span-4">
                        <label class="text-xs text-slate-400 font-bold mb-1 block">Tải ảnh lên</label>
                        <div class="flex gap-2">
                            <input type="file" name="image_upload" accept="image/*" required class="w-full bg-background-dark border border-accent-dark rounded-xl py-1.5 px-2 text-slate-200 outline-none focus:border-primary file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-primary file:text-background-dark hover:file:bg-primary/90 cursor-pointer">
                            <button type="submit" name="add_item" class="bg-primary text-background-dark font-bold px-4 py-2 rounded-xl hover:bg-primary/90 transition-all whitespace-nowrap"><span class="material-symbols-outlined">add</span></button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($items as $item): ?>
                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-4 flex flex-col items-center text-center relative group shadow-lg">
                    <a href="?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('Xóa món này? Ảnh trên máy chủ cũng sẽ bị dọn dẹp.');" class="absolute top-3 right-3 z-10 bg-red-500/10 text-red-500 p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500 hover:text-white"><span class="material-symbols-outlined text-sm">delete</span></a>
                    
                    <div class="w-32 h-32 mb-4 rounded-xl border border-accent-dark flex items-center justify-center overflow-hidden flex-shrink-0 bg-background-dark shadow-inner cursor-pointer">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="F&B" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                    </div>
                    
                    <h3 class="font-bold text-slate-100 text-lg mb-1 leading-tight"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p class="text-xs text-slate-400 mb-3 h-8"><?php echo htmlspecialchars($item['description']); ?></p>
                    <div class="w-full pt-3 border-t border-accent-dark mt-auto">
                        <span class="text-primary font-black text-xl"><?php echo number_format($item['price'], 0, ',', '.'); ?>đ</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
        </div>
    </main>
</div>
</body>
</html>