<?php
session_start();
require_once 'includes/db_connect.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// XỬ LÝ KHI NGƯỜI DÙNG BẤM LƯU PHIM
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $genre = trim($_POST['genre']);
    $duration = (int)$_POST['duration'];
    $release_date = $_POST['release_date'];
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    $trailer_url = trim($_POST['trailer_url'] ?? ''); // Thêm biến nhận Link Trailer
    $poster_url = '';

    // Xử lý Upload Ảnh
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['poster']['tmp_name'];
        $fileName = $_FILES['poster']['name'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Tạo thư mục lưu ảnh nếu chưa có
            $uploadFileDir = 'uploads/posters/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $newFileName = time() . '_' . basename($fileName);
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $poster_url = $dest_path; // Lưu link ảnh mới
            } else {
                $error = "Không thể lưu file ảnh vào thư mục máy chủ.";
            }
        } else {
            $error = "Chỉ chấp nhận các file ảnh: JPG, JPEG, PNG, GIF, WEBP.";
        }
    }

    // Tiến hành lưu Database nếu không có lỗi
    if (empty($error)) {
        try {
            // Cập nhật câu lệnh SQL thêm trường trailer_url
            $sql = "INSERT INTO movies (title, genre, duration, release_date, status, description, poster_url, trailer_url) 
                    VALUES (:title, :genre, :duration, :release_date, :status, :description, :poster_url, :trailer_url)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':genre' => $genre,
                ':duration' => $duration,
                ':release_date' => $release_date,
                ':status' => $status,
                ':description' => $description,
                ':poster_url' => $poster_url,
                ':trailer_url' => $trailer_url // Truyền giá trị vào
            ]);
            $message = "Thêm phim mới thành công!";
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
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Thêm phim mới - Admin Cinema</title>
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
    <style>body { font-family: "Be Vietnam Pro", sans-serif; } .custom-scrollbar::-webkit-scrollbar { width: 6px; } .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4 flex-1">
                <a href="admin_movies.php" class="flex items-center gap-2 text-slate-400 hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span class="font-bold text-sm">Quay lại danh sách</span>
                </a>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-slate-100 leading-none"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                        <p class="text-[10px] text-primary font-medium uppercase mt-1">Quản trị viên</p>
                    </div>
                    <div class="w-10 h-10 rounded-full border-2 border-primary/30 flex items-center justify-center bg-accent-dark text-primary font-bold">
                        <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl font-black text-slate-100 mb-2 tracking-tight uppercase">Thêm phim mới</h1>
                    <p class="text-slate-400">Nhập đầy đủ thông tin để phát hành một bộ phim mới lên hệ thống.</p>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="bg-green-500/10 border border-green-500/50 text-green-500 px-4 py-3 rounded-xl mb-6 font-medium flex items-center gap-2">
                        <span class="material-symbols-outlined">check_circle</span> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-xl mb-6 font-medium flex items-center gap-2">
                        <span class="material-symbols-outlined">error</span> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-8 shadow-2xl">
                    <form method="POST" action="add_movie.php" enctype="multipart/form-data" class="flex flex-col gap-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="md:col-span-2 space-y-6">
                                <div class="flex flex-col gap-2">
                                    <label class="text-sm font-bold text-slate-300">Tên phim</label>
                                    <input type="text" name="title" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-slate-500" placeholder="Nhập tên phim...">
                                </div>

                                <div class="grid grid-cols-2 gap-6">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-slate-300">Thể loại</label>
                                        <input type="text" name="genre" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-slate-500" placeholder="Hành động, Tâm lý...">
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-slate-300">Thời lượng (phút)</label>
                                        <input type="number" name="duration" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-slate-500" placeholder="VD: 120">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-6">
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-slate-300">Ngày khởi chiếu</label>
                                        <input type="date" name="release_date" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all color-scheme-dark">
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-slate-300">Trạng thái ban đầu</label>
                                        <select name="status" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                                            <option class="bg-surface-dark text-slate-100" value="now_showing">Đang chiếu</option>
                                            <option class="bg-surface-dark text-slate-100" value="coming_soon">Sắp chiếu</option>
                                            <option class="bg-surface-dark text-slate-100" value="stopped">Ngừng chiếu</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label class="text-sm font-bold text-slate-300">Mô tả tóm tắt nội dung phim</label>
                                    <textarea name="description" rows="5" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-slate-500" placeholder="Cốt truyện chính của phim..."></textarea>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label class="text-sm font-bold text-slate-300">Link Trailer (Youtube)</label>
                                    <input type="url" name="trailer_url" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-slate-500" placeholder="VD: https://www.youtube.com/watch?v=dQw4w9WgXcQ">
                                </div>
                            </div>

                            <div class="md:col-span-1 space-y-4">
                                <label class="text-sm font-bold text-slate-300 block">Poster Phim</label>
                                
                                <div class="w-full aspect-[2/3] rounded-xl border-2 border-dashed border-accent-dark hover:border-primary overflow-hidden bg-accent-dark/10 flex flex-col items-center justify-center relative group transition-all">
                                    <span class="material-symbols-outlined text-4xl text-slate-500 group-hover:text-primary transition-colors mb-2">add_photo_alternate</span>
                                    <span class="text-xs text-slate-500 font-medium px-4 text-center">Tải ảnh lên (Tỉ lệ 2:3)</span>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <input type="file" name="poster" required accept="image/*" class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer transition-all">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-accent-dark pt-6 mt-4 flex justify-end gap-4">
                            <a href="admin_movies.php" class="px-6 py-3 rounded-xl border border-accent-dark text-slate-300 font-bold hover:bg-accent-dark transition-all">Hủy bỏ</a>
                            <button type="submit" class="bg-primary text-background-dark font-bold px-8 py-3 rounded-xl shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] hover:bg-primary/90 transition-all active:scale-95 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">add_circle</span> Lưu phim mới
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