<?php
session_start();
require_once 'includes/db_connect.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

// Chỉ xử lý khi có dữ liệu POST gửi sang từ trang thanh toán
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"];
    $movie_id = $_POST['movie_id'];
    
    // ĐÃ THÊM: Hứng ID Rạp chiếu từ trang thanh toán
    $cinema_id = $_POST['cinema_id'] ?? 0; 
    
    $selected_seats = $_POST['selected_seats'];
    $total_price = $_POST['total_price'];
    
    // Nhận dữ liệu động từ trang chọn ghế/thanh toán truyền sang
    $payment_method = $_POST['payment_method'] ?? 'Thẻ/Ví điện tử';
    if ($payment_method == 'momo') $payment_method = "Ví MoMo";
    if ($payment_method == 'zalopay') $payment_method = "ZaloPay";

    $show_time = $_POST['show_time'] ?? "Đang cập nhật"; 
    $room_name = $_POST['room_name'] ?? "Đang cập nhật";

    try {
        // 1. Lưu đơn hàng vào Database (ĐÃ THÊM cinema_id vào câu lệnh INSERT)
        $sql = "INSERT INTO orders (user_id, movie_id, cinema_id, show_time, room_name, seat_numbers, total_price, payment_method, status) 
                VALUES (:user_id, :movie_id, :cinema_id, :show_time, :room_name, :seat_numbers, :total_price, :payment_method, 'completed')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':movie_id' => $movie_id,
            ':cinema_id' => $cinema_id, // Truyền ID rạp vào CSDL
            ':show_time' => $show_time,
            ':room_name' => $room_name, 
            ':seat_numbers' => $selected_seats,
            ':total_price' => $total_price,
            ':payment_method' => $payment_method
        ]);

        // 2. Lấy ID đơn hàng vừa tạo để làm Mã vé
        $order_id = $pdo->lastInsertId();
        // Tạo mã vé đẹp dạng CGV0000001
        $order_code = "CGV" . str_pad($order_id, 8, "0", STR_PAD_LEFT);

        // Tạo link kiểm tra vé tự động bắt theo tên miền của bạn (localhost hoặc domain thật)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'];
        $verify_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']) . "/kiemtra_ve.php?code=" . $order_code;
        $qr_data = urlencode($verify_url); // Mã hóa URL để đưa vào API ảnh QR

        // 3. Lấy thông tin phim để in lên vé
        $stmt_movie = $pdo->prepare("SELECT * FROM movies WHERE id = :id");
        $stmt_movie->execute(['id' => $movie_id]);
        $movie = $stmt_movie->fetch();

    } catch (PDOException $e) {
        die("Lỗi xử lý đơn hàng: " . $e->getMessage());
    }

} else {
    // Nếu truy cập trực tiếp bằng URL, đá về trang chủ
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Thanh toán thành công - GOLD CINEMA</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2cc0d",
                        "background-light": "#f8f8f5",
                        "background-dark": "#1a180a", /* Chỉnh lại bg cho đồng bộ dark mode */
                    },
                    fontFamily: {
                        "display": ["Be Vietnam Pro"]
                    }
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; background-color: #1a180a;}
        .ticket-shape {
            mask-image: radial-gradient(circle at 0 50%, transparent 12px, black 13px), radial-gradient(circle at 100% 50%, transparent 12px, black 13px);
            mask-position: left, right;
            mask-size: 50% 100%;
            mask-repeat: no-repeat;
        }
        .ticket-divider {
            border-top: 2px dashed rgba(242, 204, 13, 0.2);
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
    <div class="layout-container flex h-full grow flex-col">
        
        <header class="flex items-center justify-between border-b border-primary/20 px-6 py-4 md:px-20 lg:px-40 bg-background-dark/80 backdrop-blur-md sticky top-0 z-50">
            <a href="index.php" class="flex items-center gap-4 hover:opacity-80">
                <div class="text-primary size-8">
                    <span class="material-symbols-outlined text-3xl">movie</span>
                </div>
                <h2 class="text-slate-100 text-xl font-black tracking-tight uppercase">GOLD CINEMA</h2>
            </a>
            <div class="flex items-center gap-4">
                <div class="hidden sm:block text-right">
                    <p class="text-sm font-bold"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name']))[count(explode(' ', trim($_SESSION['user_name']))) - 1]); ?></p>
                    <p class="text-[10px] text-primary font-black uppercase tracking-widest">Khách hàng</p>
                </div>
                <div class="size-10 rounded-full border-2 border-primary/30 bg-[#2a2614] text-primary flex items-center justify-center font-bold">
                    <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                </div>
            </div>
        </header>

        <main class="flex-1 flex flex-col items-center py-10 px-4 md:px-8">
            <div class="flex flex-col items-center text-center mb-10">
                <div class="size-20 bg-primary/20 rounded-full flex items-center justify-center mb-4 border-2 border-primary/40">
                    <span class="material-symbols-outlined text-primary text-5xl">check_circle</span>
                </div>
                <h1 class="text-slate-100 text-3xl md:text-4xl font-black mb-2 uppercase tracking-tight">Thanh toán thành công!</h1>
                <p class="text-primary/70 text-sm font-medium">
                    Mã đơn hàng: #<?php echo $order_code; ?> • <?php echo date('d/m/Y'); ?>
                </p>
            </div>

            <div class="w-full max-w-[450px] relative">
                <div class="bg-zinc-900 rounded-xl overflow-hidden shadow-2xl border border-primary/20 flex flex-col ticket-shape">
                    
                    <div class="p-6 pb-4">
                        <div class="flex gap-4 items-start">
                            <div class="w-24 h-36 rounded-lg bg-cover bg-center shadow-md flex-shrink-0 border border-primary/10" style="background-image: url('<?php echo htmlspecialchars($movie['poster_url']); ?>')"></div>
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-bold px-2 py-0.5 bg-primary text-background-dark rounded w-fit mb-1">2D Phụ Đề</span>
                                <h3 class="text-2xl font-black text-slate-100 leading-tight uppercase"><?php echo htmlspecialchars($movie['title']); ?></h3>
                                <p class="text-sm text-slate-400"><?php echo htmlspecialchars($movie['genre']); ?></p>
                                <div class="flex items-center gap-1 mt-2 text-primary">
                                    <span class="material-symbols-outlined text-sm">stars</span>
                                    <span class="text-xs font-bold uppercase tracking-widest">Suất chiếu đặc biệt</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative py-2 px-6">
                        <div class="ticket-divider"></div>
                    </div>

                    <div class="p-6 grid grid-cols-2 gap-y-4 gap-x-2">
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-0.5">Phòng chiếu</p>
                            <p class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($room_name); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-0.5">Phương thức</p>
                            <p class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($payment_method); ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-0.5">Thời gian</p>
                            <p class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($show_time); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mb-0.5">Ghế</p>
                            <p class="text-sm font-black text-primary text-lg"><?php echo htmlspecialchars($selected_seats); ?></p>
                        </div>
                    </div>

                    <div class="relative py-2">
                        <div class="ticket-divider"></div>
                        <div class="absolute -left-4 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-[#1a180a] border-r border-primary/20"></div>
                        <div class="absolute -right-4 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-[#1a180a] border-l border-primary/20"></div>
                    </div>

                    <div class="p-8 flex flex-col items-center justify-center bg-zinc-800/50">
                        <div class="bg-white p-3 rounded-lg shadow-sm mb-4">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $qr_data; ?>" alt="QR Code" class="w-32 h-32">
                        </div>
                        <p class="text-xs text-slate-400 font-medium text-center px-8 italic">Vui lòng xuất trình mã QR này tại cửa vào rạp để nhân viên soát vé.</p>
                    </div>

                    <div class="bg-primary p-4 flex justify-between items-center">
                        <span class="text-background-dark text-sm font-black uppercase tracking-tight">Đã thanh toán</span>
                        <span class="text-background-dark text-2xl font-black"><?php echo number_format($total_price, 0, ',', '.'); ?>đ</span>
                    </div>
                </div>
            </div>

            <div class="w-full max-w-[450px] mt-10 flex flex-col gap-3">
                <a href="index.php" class="w-full h-14 bg-primary text-background-dark font-bold text-lg rounded-xl flex items-center justify-center gap-2 hover:bg-primary/90 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]">
                    <span class="material-symbols-outlined">home</span> Về trang chủ
                </a>
            </div>
        </main>
    </div>
</div>
</body>
</html>