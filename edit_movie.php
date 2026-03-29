<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

$movie_id = $_GET['id'] ?? ($_POST['id'] ?? 0);
if (!$movie_id) {
    header("Location: admin_movies.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $genre = trim($_POST['genre']);
    $duration = (int)$_POST['duration'];
    $release_date = $_POST['release_date'];
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    $trailer_url = trim($_POST['trailer_url'] ?? '');
    
    $existing_poster = $_POST['existing_poster']; 
    $existing_banner = $_POST['existing_banner'] ?? ''; // Nhận link banner cũ
    
    $poster_url = $existing_poster; 
    $banner_url = $existing_banner; 

    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // 1. Cập nhật Poster mới (Nếu có)
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = 'uploads/posters/';
            if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
            $newFileName = time() . '_poster_' . basename($_FILES['poster']['name']);
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($_FILES['poster']['tmp_name'], $dest_path)) {
                $poster_url = $dest_path; 
            } else $error = "Lỗi lưu Poster.";
        } else $error = "Sai định dạng Poster.";
    }

    // 2. Cập nhật Banner mới (Nếu có) - THÊM MỚI
    if (empty($error) && isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = 'uploads/banners/'; // Lưu vào thư mục banners
            if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
            $newFileName = time() . '_banner_' . basename($_FILES['banner']['name']);
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $dest_path)) {
                $banner_url = $dest_path; 
            } else $error = "Lỗi lưu Banner.";
        } else $error = "Sai định dạng Banner.";
    }

    // 3. Cập nhật CSDL
    if (empty($error)) {
        try {
            // ĐÃ THÊM: banner_url vào UPDATE
            $sql = "UPDATE movies SET 
                        title = :title, genre = :genre, duration = :duration, 
                        release_date = :release_date, status = :status, description = :description, 
                        poster_url = :poster_url, trailer_url = :trailer_url, banner_url = :banner_url 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title, ':genre' => $genre, ':duration' => $duration,
                ':release_date' => $release_date, ':status' => $status, ':description' => $description,
                ':poster_url' => $poster_url, ':trailer_url' => $trailer_url, ':banner_url' => $banner_url,
                ':id' => $movie_id
            ]);
            $message = "Cập nhật phim thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi CSDL: " . $e->getMessage();
        }
    }
}

// LẤY DỮ LIỆU CŨ HIỂN THỊ LÊN FORM
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = :id");
$stmt->execute(['id' => $movie_id]);
$movie = $stmt->fetch();

if (!$movie) { header("Location: admin_movies.php"); exit; }
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Chỉnh sửa phim - Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"primary":"#f2cc0d","background-dark":"#1a180b","surface-dark":"#2a2614","accent-dark":"#403a1e"},fontFamily:{"display":["Be Vietnam Pro"]}}}}</script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; } .custom-scrollbar::-webkit-scrollbar { width: 6px; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }</style>
</head>
<body class="bg-background-dark text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    <?php require_once 'includes/admin_sidebar.php'; ?>
    <main class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <a href="admin_movies.php" class="flex items-center gap-2 text-slate-400 hover:text-primary"><span class="material-symbols-outlined">arrow_back</span><span class="font-bold text-sm">Quay lại</span></a>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            <div class="max-w-5xl mx-auto">
                <div class="mb-8"><h1 class="text-3xl font-black text-slate-100 mb-2 uppercase">Chỉnh sửa phim</h1></div>

                <?php if(!empty($message)): ?><div class="bg-green-500/10 text-green-500 px-4 py-3 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?php echo $message; ?></div><?php endif; ?>
                <?php if(!empty($error)): ?><div class="bg-red-500/10 text-red-500 px-4 py-3 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?php echo $error; ?></div><?php endif; ?>

                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-8 shadow-2xl">
                    <form method="POST" action="edit_movie.php?id=<?php echo $movie_id; ?>" enctype="multipart/form-data" class="flex flex-col gap-6">
                        <input type="hidden" name="id" value="<?php echo $movie_id; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="md:col-span-2 space-y-6">
                                <div class="flex flex-col gap-2"><label class="text-sm font-bold text-slate-300">Tên phim</label><input type="text" name="title" required value="<?php echo htmlspecialchars($movie['title']); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none"></div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="flex flex-col gap-2"><label class="text-sm font-bold text-slate-300">Thể loại</label><input type="text" name="genre" required value="<?php echo htmlspecialchars($movie['genre']); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none"></div>
                                    <div class="flex flex-col gap-2"><label class="text-sm font-bold text-slate-300">Thời lượng (phút)</label><input type="number" name="duration" required value="<?php echo htmlspecialchars($movie['duration']); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="flex flex-col gap-2"><label class="text-sm font-bold text-slate-300">Ngày chiếu</label><input type="date" name="release_date" value="<?php echo htmlspecialchars($movie['release_date']); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none color-scheme-dark"></div>
                                    <div class="flex flex-col gap-2">
                                        <label class="text-sm font-bold text-slate-300">Trạng thái</label>
                                        <select name="status" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none">
                                            <option value="now_showing" class="bg-surface-dark" <?php echo ($movie['status'] == 'now_showing') ? 'selected' : ''; ?>>Đang chiếu</option>
                                            <option value="coming_soon" class="bg-surface-dark" <?php echo ($movie['status'] == 'coming_soon') ? 'selected' : ''; ?>>Sắp chiếu</option>
                                            <option value="stopped" class="bg-surface-dark" <?php echo ($movie['status'] == 'stopped') ? 'selected' : ''; ?>>Ngừng chiếu</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2"><label class="text-sm font-bold text-slate-300">Mô tả</label><textarea name="description" rows="4" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none"><?php echo htmlspecialchars($movie['description']); ?></textarea></div>
                                <div class="flex flex-col gap-2"><label class="text-sm font-bold text-slate-300">Link Trailer (Youtube)</label><input type="url" name="trailer_url" value="<?php echo htmlspecialchars($movie['trailer_url'] ?? ''); ?>" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 focus:border-primary outline-none"></div>
                            </div>

                            <div class="md:col-span-1 space-y-6">
                                <div class="space-y-3">
                                    <label class="text-sm font-bold text-slate-300 block flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">portrait</span> Poster (Dọc)</label>
                                    <div class="w-full aspect-[2/3] rounded-xl border border-accent-dark overflow-hidden bg-black flex items-center justify-center">
                                        <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster" class="w-full h-full object-cover opacity-80">
                                    </div>
                                    <input type="hidden" name="existing_poster" value="<?php echo htmlspecialchars($movie['poster_url']); ?>">
                                    <input type="file" name="poster" accept="image/*" class="w-full text-sm text-slate-400 file:py-2 file:px-4 file:rounded-xl file:border-0 file:font-bold file:bg-primary/10 file:text-primary cursor-pointer">
                                </div>

                                <div class="border-t border-accent-dark pt-4"></div>

                                <div class="space-y-3">
                                    <label class="text-sm font-bold text-slate-300 block flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">panorama</span> Banner (Ngang)</label>
                                    
                                    <div class="w-full aspect-[16/9] rounded-xl border border-accent-dark overflow-hidden bg-black flex items-center justify-center">
                                        <?php if(!empty($movie['banner_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($movie['banner_url']); ?>" alt="Banner" class="w-full h-full object-cover opacity-80">
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500 text-center px-4">Chưa có banner ngang<br>(Đang dùng Poster làm nền)</span>
                                        <?php endif; ?>
                                    </div>

                                    <input type="hidden" name="existing_banner" value="<?php echo htmlspecialchars($movie['banner_url'] ?? ''); ?>">
                                    <input type="file" name="banner" accept="image/*" class="w-full text-sm text-slate-400 file:py-2 file:px-4 file:rounded-xl file:border-0 file:font-bold file:bg-primary/10 file:text-primary cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-accent-dark pt-6 flex justify-end gap-4">
                            <button type="submit" class="bg-primary text-background-dark font-bold px-8 py-3 rounded-xl shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] hover:bg-primary/90 flex items-center gap-2"><span class="material-symbols-outlined text-sm">save</span> Cập nhật phim</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>