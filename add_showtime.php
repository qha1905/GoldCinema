<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// Lấy danh sách phim đang chiếu
$movies = $pdo->query("SELECT id, title, duration FROM movies WHERE status != 'stopped' ORDER BY id DESC")->fetchAll();

// ĐÃ THAY ĐỔI: Lấy thêm cột total_rooms từ bảng cinemas
$cinemas = $pdo->query("SELECT id, name, total_rooms FROM cinemas WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $movie_id = (int)$_POST['movie_id'];
    $cinema_id = (int)$_POST['cinema_id'];
    $room_name = trim($_POST['room_name']);
    $show_date = $_POST['show_date'];
    $start_time = $_POST['start_time'];
    $status = $_POST['status'];

    if (!$movie_id || !$cinema_id || empty($room_name) || empty($show_date) || empty($start_time)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        try {
            // 1. Tự động tính Giờ kết thúc dựa vào Thời lượng phim
            $movie_duration = 120; // Mặc định nếu không tìm thấy
            foreach ($movies as $m) {
                if ($m['id'] == $movie_id) {
                    $movie_duration = $m['duration'];
                    break;
                }
            }
            $end_time = date('H:i:s', strtotime("+$movie_duration minutes", strtotime($start_time)));

            // 2. KIỂM TRA TRÙNG LỊCH CHIẾU
            $sql_check = "
                SELECT COUNT(*) FROM showtimes 
                WHERE cinema_id = :cinema_id 
                  AND room_name = :room_name 
                  AND show_date = :show_date 
                  AND start_time < :new_end_time 
                  AND end_time > :new_start_time
            ";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([
                ':cinema_id' => $cinema_id,
                ':room_name' => $room_name,
                ':show_date' => $show_date,
                ':new_end_time' => $end_time,
                ':new_start_time' => $start_time
            ]);

            $conflict_count = $stmt_check->fetchColumn();

            if ($conflict_count > 0) {
                $time_format = date('H:i', strtotime($start_time)) . ' đến ' . date('H:i', strtotime($end_time));
                $error = "Lỗi: <b>Phòng {$room_name}</b> đã có phim khác đang chiếu hoặc dọn dẹp trong khoảng thời gian từ <b>{$time_format}</b>. Vui lòng đổi giờ chiếu hoặc chọn phòng khác!";
            } else {
                // Lưu vào Database
                $sql = "INSERT INTO showtimes (movie_id, cinema_id, room_name, show_date, start_time, end_time, status) 
                        VALUES (:movie_id, :cinema_id, :room_name, :show_date, :start_time, :end_time, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':movie_id' => $movie_id,
                    ':cinema_id' => $cinema_id,
                    ':room_name' => $room_name,
                    ':show_date' => $show_date,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time,
                    ':status' => $status
                ]);
                $message = "Đã tạo lịch chiếu mới thành công!";
            }
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
    <title>Tạo lịch chiếu - Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>body { font-family: "Be Vietnam Pro", sans-serif; } ::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }</style>
</head>
<body class="bg-background-dark text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10">
            <div class="flex items-center gap-4">
                <a href="admin_showtimes.php" class="flex items-center gap-2 text-slate-400 hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span class="font-bold text-sm">Quay lại lịch chiếu</span>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl font-black text-slate-100 uppercase">Tạo lịch chiếu mới</h1>
                    <p class="text-slate-400">Thiết lập thời gian và phòng chiếu cho phim.</p>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="bg-green-500/10 border border-green-500/20 text-green-500 px-4 py-3 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if(!empty($error)): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-500 px-4 py-3 rounded-xl mb-6 font-medium flex items-start gap-2"><span class="material-symbols-outlined">error</span> <span><?php echo $error; ?></span></div>
                <?php endif; ?>

                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-8 shadow-2xl">
                    <form method="POST" action="add_showtime.php" class="flex flex-col gap-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Chọn Phim</label>
                                <select name="movie_id" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="">-- Chọn phim --</option>
                                    <?php foreach ($movies as $m): ?>
                                        <option class="bg-surface-dark" value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['title']); ?> (<?php echo $m['duration']; ?>p)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Cụm Rạp</label>
                                <select name="cinema_id" id="cinemaSelect" onchange="updateRooms()" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="" data-rooms="0">-- Chọn rạp --</option>
                                    <?php foreach ($cinemas as $c): ?>
                                        <option class="bg-surface-dark" value="<?php echo $c['id']; ?>" data-rooms="<?php echo $c['total_rooms']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Phòng chiếu</label>
                                <select name="room_name" id="roomSelect" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark text-slate-400" value="">-- Vui lòng chọn rạp trước --</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Ngày chiếu</label>
                                <input type="date" name="show_date" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none color-scheme-dark cursor-pointer">
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Giờ bắt đầu</label>
                                <input type="time" name="start_time" required class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none color-scheme-dark cursor-pointer">
                                <span class="text-xs text-slate-500">* Giờ kết thúc sẽ được tự động tính dựa trên thời lượng phim.</span>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-bold text-slate-300">Trạng thái vé</label>
                                <select name="status" class="w-full bg-accent-dark/30 border border-accent-dark rounded-xl py-3 px-4 text-slate-100 focus:border-primary outline-none cursor-pointer">
                                    <option class="bg-surface-dark" value="upcoming">Sắp chiếu / Đang mở bán</option>
                                    <option class="bg-surface-dark" value="showing">Đang chiếu</option>
                                    <option class="bg-surface-dark" value="sold_out">Hết vé</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-accent-dark pt-6 mt-4 flex justify-end gap-4">
                            <button type="submit" class="bg-primary text-background-dark font-bold px-8 py-3 rounded-xl shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] hover:bg-primary/90 transition-all active:scale-95 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">event_available</span> Lưu lịch chiếu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function updateRooms() {
        const cinemaSelect = document.getElementById('cinemaSelect');
        const roomSelect = document.getElementById('roomSelect');
        
        // Lấy số lượng phòng của rạp đang được chọn thông qua data-rooms
        const selectedOption = cinemaSelect.options[cinemaSelect.selectedIndex];
        const totalRooms = parseInt(selectedOption.getAttribute('data-rooms')) || 0;

        // Reset danh sách phòng
        roomSelect.innerHTML = '';

        // Nếu chưa chọn rạp
        if (totalRooms === 0) {
            roomSelect.innerHTML = '<option class="bg-surface-dark text-slate-400" value="">-- Vui lòng chọn rạp trước --</option>';
            return;
        }

        // Bắt đầu thêm các option mới dựa trên tổng số phòng
        roomSelect.innerHTML = '<option class="bg-surface-dark text-slate-400" value="">-- Chọn phòng chiếu --</option>';
        
        for (let i = 1; i <= totalRooms; i++) {
            // Thêm số 0 ở trước nếu < 10 (VD: P01, P02...)
            let roomCode = 'P' + (i < 10 ? '0' + i : i);
            let roomLabel = 'Phòng ' + (i < 10 ? '0' + i : i) + ' (' + roomCode + ')';
            
            let option = document.createElement('option');
            option.value = roomCode;
            option.className = 'bg-surface-dark text-slate-100';
            option.textContent = roomLabel;
            
            roomSelect.appendChild(option);
        }
    }
</script>
</body>
</html>