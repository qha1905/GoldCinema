<?php
// 1. Phải kết nối DB và xử lý dữ liệu trước
session_start(); // Cần gọi trước nếu muốn kiểm tra redirect
require_once 'includes/db_connect.php';

$movie_id = isset($_GET['id']) ? (int)$_GET['id'] : 1; // Mặc định ID 1 nếu không truyền params

// Truy vấn thông tin phim
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $movie_id]);
$movie = $stmt->fetch();

if (!$movie) {
    header("Location: index.php");
    exit;
}

// 2. Bắt đầu xuất giao diện thông qua header.php
require_once 'includes/header.php';
?>

<section class="relative w-full aspect-[16/9] md:aspect-[21/9] min-h-[500px] overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($movie['poster_url'] ?? 'https://lh3.googleusercontent.com/aida-public/AB6AXuD5EpnUxkODP9CQD9wFajpPKFgYOwSAJsbECDvqEXkkTVTD2d8XaeLPmrrQYcLulGzK76xsvkJmC0_cvXSStfSzjX-XUY3iV7A4NvuXhxEQIZ1cVnul4tvBYkiDAowOEPNj-gG78DHlwpkmocU9LTamHjIxPU7b8cJpvY2SH-b5sf1KuNl6xVFlqVrs5iwoQkdPQp5YwRZi4bkaREi7VzFeU189SqhHUfbrwlKxGPllMwnm4z35q8pqjxPzS53MS9zEQuLt1SA2X_o'); ?>');">
        <div class="absolute inset-0 bg-gradient-to-t from-background-dark via-background-dark/60 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-background-dark via-background-dark/40 to-transparent"></div>
    </div>
    <div class="relative h-full max-w-7xl mx-auto px-6 flex flex-col justify-end pb-12 md:pb-20">
        <div class="flex flex-col gap-4 max-w-2xl">
            <div class="flex items-center gap-2">
                <span class="bg-primary text-background-dark px-2 py-1 rounded text-xs font-bold uppercase tracking-wider">T18</span>
                <span class="text-slate-300 text-sm font-medium">
                    <?php echo htmlspecialchars($movie['duration']); ?> phút • <?php echo htmlspecialchars($movie['genre']); ?>
                </span>
            </div>
            
            <h1 class="text-5xl md:text-7xl font-black text-white leading-none tracking-tighter uppercase">
                <?php echo htmlspecialchars($movie['title']); ?>
            </h1>
            
            <p class="text-lg text-slate-200 leading-relaxed font-light">
                <?php echo htmlspecialchars($movie['description']); ?>
            </p>
            
            <div class="flex flex-wrap items-center gap-6 mt-4">
                <div class="flex flex-col">
                    <span class="text-xs text-primary uppercase font-bold tracking-widest">Đánh giá</span>
                    <span class="text-white font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-primary text-sm">star</span>
                        <?php echo htmlspecialchars($movie['rating']); ?> / 5.0
                    </span>
                </div>
                <div class="flex flex-col border-l border-white/20 pl-6">
                    <span class="text-xs text-primary uppercase font-bold tracking-widest">Khởi chiếu</span>
                    <span class="text-white font-medium">
                        <?php echo date('d/m/Y', strtotime($movie['release_date'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="flex gap-4 mt-8">
                <a href="chonGhe.php?id=<?php echo $movie['id']; ?>" class="bg-primary text-background-dark px-8 py-4 rounded-lg font-bold text-lg hover:bg-white transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined">confirmation_number</span>
                    Đặt vé ngay
                </a>
                <button class="bg-white/10 backdrop-blur-md text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white/20 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined">play_circle</span>
                    Xem Trailer
                </button>
            </div>
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-6 py-16">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
        <div>
            <h2 class="text-3xl font-bold text-white mb-2">Lịch Chiếu</h2>
            <p class="text-slate-400">Chọn rạp và suất chiếu phù hợp với bạn</p>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
            <button class="flex flex-col items-center justify-center min-w-[70px] h-20 rounded-xl bg-primary text-background-dark font-bold shadow-lg shadow-primary/20">
                <span class="text-xs uppercase">Th 2</span>
                <span class="text-xl">12</span>
            </button>
            <button class="flex flex-col items-center justify-center min-w-[70px] h-20 rounded-xl bg-white/5 border border-white/10 text-slate-300 font-bold hover:border-primary/50 transition-all">
                <span class="text-xs uppercase">Th 3</span>
                <span class="text-xl">13</span>
            </button>
        </div>
    </div>
    </section>

<?php
// 3. Kết thúc giao diện bằng footer.php
require_once 'includes/footer.php';
?>