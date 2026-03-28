<?php
session_start();
require_once 'includes/db_connect.php';

// YÊU CẦU ĐĂNG NHẬP: Bắt buộc khách hàng phải login trước khi chọn suất
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["user_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

// 1. Nhận ID phim và thông tin suất chiếu từ URL (Trang chon_suat.php truyền sang)
$movie_id = $_GET['id'] ?? 1;
$show_time_get = $_GET['time'] ?? 'Chưa xác định';
$room_name_get = $_GET['room'] ?? 'Phòng Tiêu Chuẩn';
$show_id = $_GET['show_id'] ?? 0;

// 2. Lấy thông tin Phim
$stmt_movie = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt_movie->execute([$movie_id]);
$movie = $stmt_movie->fetch();

if (!$movie) {
    die("Không tìm thấy phim!");
}

// 3. Lấy tên rạp dựa vào suất chiếu
$cinema_name = "Hệ thống Rạp";
if ($show_id) {
    $stmt_cinema = $pdo->prepare("
        SELECT c.name FROM showtimes s 
        JOIN cinemas c ON s.cinema_id = c.id 
        WHERE s.id = ?
    ");
    $stmt_cinema->execute([$show_id]);
    $c_name = $stmt_cinema->fetchColumn();
    if ($c_name) $cinema_name = $c_name;
}

// 4. LẤY DANH SÁCH GHẾ ĐÃ ĐẶT
$stmt_booked = $pdo->prepare("SELECT seat_numbers FROM orders WHERE movie_id = ? AND show_time = ? AND room_name = ? AND status = 'completed'");
$stmt_booked->execute([$movie_id, $show_time_get, $room_name_get]);
$booked_rows = $stmt_booked->fetchAll();

$booked_seats = [];
foreach ($booked_rows as $row) {
    $seats = explode(',', $row['seat_numbers']);
    foreach ($seats as $s) {
        $booked_seats[] = trim($s);
    }
}

// Cấu hình Bảng Giá Vé MỚI
$price_regular = 95000;
$price_vip = 100000;
$price_couple = 230000; // Giá ghế đôi
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Chọn ghế - <?php echo htmlspecialchars($movie['title']); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180a", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180a; }
        .screen-glow { box-shadow: 0 10px 40px -10px rgba(242,204,13,0.3); }
        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
        
        /* Hiệu ứng ghế chung */
        .seat { transition: all 0.2s ease-in-out; position: relative; }
        .seat:not(.booked):active { transform: scale(0.9); }
        
        /* 1. Ghế Thường */
        .seat-regular { background-color: #334155; color: #cbd5e1; border: 1px solid transparent; } 
        .seat-regular:hover:not(.booked):not(.selected) { background-color: #475569; }
        
        /* 2. Ghế VIP */
        .seat-vip { background-color: rgba(120, 53, 15, 0.4); border: 1px solid rgba(245, 158, 11, 0.5); color: #fbbf24; } 
        .seat-vip:hover:not(.booked):not(.selected) { background-color: rgba(120, 53, 15, 0.8); }

        /* 3. Ghế Đôi (Couple) */
        .seat-couple { background-color: rgba(236, 72, 153, 0.15); border: 1px solid rgba(236, 72, 153, 0.5); color: #f472b6; } 
        .seat-couple:hover:not(.booked):not(.selected) { background-color: rgba(236, 72, 153, 0.35); }
        
        /* Trạng thái Đang Chọn & Đã Bán */
        .seat.selected { background-color: #f2cc0d !important; color: #1a180a !important; border-color: #f2cc0d !important; font-weight: bold; }
        .seat.booked { background-color: #0f172a !important; color: #334155 !important; border: 1px dashed #334155 !important; cursor: not-allowed; }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col">

    <header class="h-20 border-b border-border-dark bg-background-dark/80 backdrop-blur-md sticky top-0 z-50 px-6 flex items-center gap-4">
        <a href="javascript:history.back()" class="flex items-center gap-2 text-primary hover:text-white transition-colors group">
            <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform">arrow_back</span>
        </a>
        <h1 class="text-xl font-bold tracking-tight"><?php echo htmlspecialchars($movie['title']); ?></h1>
    </header>

    <main class="flex-1 max-w-[1400px] mx-auto w-full p-4 md:p-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-8 flex flex-col items-center">
            
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-3 mb-8 text-xs font-bold text-slate-300 bg-surface-dark py-3 px-6 rounded-full border border-accent-dark">
                <div class="flex items-center gap-2"><div class="w-5 h-5 rounded seat-regular"></div> Thường (<?php echo number_format($price_regular, 0, ',', '.'); ?>đ)</div>
                <div class="flex items-center gap-2"><div class="w-5 h-5 rounded seat-vip"></div> VIP (<?php echo number_format($price_vip, 0, ',', '.'); ?>đ)</div>
                <div class="flex items-center gap-2"><div class="w-8 h-5 rounded seat-couple flex items-center justify-center"><span class="material-symbols-outlined text-[12px]">favorite</span></div> Đôi (<?php echo number_format($price_couple, 0, ',', '.'); ?>đ)</div>
                <div class="flex items-center gap-2 ml-4 border-l border-accent-dark pl-4"><div class="w-5 h-5 rounded bg-primary"></div> Đang chọn</div>
                <div class="flex items-center gap-2"><div class="w-5 h-5 rounded booked"></div> Đã bán</div>
            </div>

            <div class="w-full max-w-3xl mb-16">
                <div class="w-full h-1.5 bg-primary/50 rounded-full screen-glow relative">
                    <div class="absolute inset-x-0 -bottom-8 text-center text-xs font-black text-slate-500 tracking-[0.8em] uppercase">Màn Hình</div>
                </div>
            </div>

            <div class="w-full overflow-x-auto custom-scrollbar pb-8">
                <div class="flex flex-col gap-2 min-w-[700px] items-center">
                    <?php
                    $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
                    $cols = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
                    $vip_cols = [3, 4, 5, 6, 7, 8]; // Khu vực trung tâm

                    // In các hàng từ A đến I
                    foreach ($rows as $r) {
                        echo '<div class="flex items-center justify-center gap-1.5">';
                        echo '<div class="w-6 text-center text-slate-500 font-bold text-sm mr-2">'.$r.'</div>';
                        
                        foreach ($cols as $c) {
                            $seat_id = $r . $c;
                            $is_booked = in_array($seat_id, $booked_seats);
                            
                            // Logic: 3 hàng đầu (A,B,C) là ghế thường, còn lại xét xem có nằm ở trung tâm không
                            if (in_array($r, ['A', 'B', 'C'])) {
                                $is_vip = false;
                            } else {
                                $is_vip = in_array($c, $vip_cols);
                            }
                            
                            $seat_class = 'seat w-10 h-10 rounded-t-lg rounded-b-sm flex items-center justify-center text-xs font-semibold cursor-pointer select-none ';
                            
                            if ($is_booked) {
                                $seat_class .= 'booked';
                                $price = 0;
                            } else {
                                $seat_class .= $is_vip ? 'seat-vip' : 'seat-regular';
                                $price = $is_vip ? $price_vip : $price_regular;
                            }

                            // Tạo lối đi (corridor) sau cột 2 và cột 8
                            $margin_class = ($c == 2 || $c == 8) ? 'mr-8 md:mr-12' : '';

                            echo '<div class="'.$seat_class.' '.$margin_class.'" data-seat="'.$seat_id.'" data-price="'.$price.'" data-booked="'.($is_booked ? 'true' : 'false').'">'.$seat_id.'</div>';
                        }
                        echo '</div>';
                    }

                    // In hàng J - Ghế Đôi (Couple Row)
                    $couple_row = 'J';
                    $couple_seats = [1, 2, 3, 4, 5];
                    echo '<div class="flex items-center justify-center gap-1.5 mt-6">'; // Khoảng cách rộng hơn 1 chút
                    echo '<div class="w-6 text-center text-[#f472b6] font-bold text-sm mr-2">'.$couple_row.'</div>';
                    
                    foreach ($couple_seats as $c) {
                        $seat_id = $couple_row . $c;
                        $is_booked = in_array($seat_id, $booked_seats);
                        
                        // Ghế đôi chiếm chiều rộng = 2 ghế thường + gap
                        $seat_class = 'seat h-11 rounded-t-xl rounded-b-md flex items-center justify-center text-xs font-bold cursor-pointer select-none w-[86px] '; 
                        
                        if ($is_booked) {
                            $seat_class .= 'booked';
                            $price = 0;
                        } else {
                            $seat_class .= 'seat-couple';
                            $price = $price_couple;
                        }

                        // Lối đi căn chuẩn với các hàng phía trên
                        $margin_class = ($c == 1 || $c == 4) ? 'mr-8 md:mr-12' : '';

                        echo '<div class="'.$seat_class.' '.$margin_class.'" data-seat="'.$seat_id.'" data-price="'.$price.'" data-booked="'.($is_booked ? 'true' : 'false').'">
                                <span class="material-symbols-outlined text-[14px] mr-1">favorite</span> '.$seat_id.'
                              </div>';
                    }
                    echo '</div>';
                    ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4">
            <form id="bookingForm" action="thanhtoan.php" method="POST" class="bg-surface-dark border border-accent-dark rounded-2xl p-6 sticky top-28 shadow-2xl">
                
                <div class="flex gap-4 mb-6 pb-6 border-b border-accent-dark">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster" class="w-16 h-24 rounded object-cover shadow-md border border-accent-dark">
                    <div>
                        <h3 class="text-lg font-black text-primary uppercase leading-tight mb-1"><?php echo htmlspecialchars($movie['title']); ?></h3>
                        <p class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($cinema_name); ?></p>
                        <p class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($room_name_get); ?> • <?php echo htmlspecialchars($show_time_get); ?></p>
                    </div>
                </div>

                <div class="mb-6 min-h-[60px]">
                    <p class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-2">Ghế đã chọn</p>
                    <p id="selectedSeatsText" class="text-base font-bold text-primary leading-relaxed">Chưa chọn ghế</p>
                </div>

                <div class="flex items-end justify-between mb-8 bg-background-dark p-4 rounded-xl border border-accent-dark">
                    <p class="text-sm text-slate-400 font-bold uppercase tracking-wider">Tổng cộng</p>
                    <p id="totalPriceText" class="text-2xl font-black text-primary">0đ</p>
                </div>

                <input type="hidden" name="movie_id" value="<?php echo $movie_id; ?>">
                <input type="hidden" name="show_time" value="<?php echo htmlspecialchars($show_time_get); ?>">
                <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room_name_get); ?>">
                <input type="hidden" name="selected_seats" id="inputSeats" value="">
                <input type="hidden" name="total_price" id="inputTotal" value="0">

                <button type="submit" id="btnSubmit" disabled class="w-full bg-primary text-background-dark py-4 rounded-xl font-bold text-lg hover:bg-primary/90 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.39)] disabled:opacity-50 disabled:shadow-none disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    Tiếp tục thanh toán <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
            </form>
        </div>

    </main>

    <script>
        const seats = document.querySelectorAll('.seat:not(.booked)');
        const selectedSeatsText = document.getElementById('selectedSeatsText');
        const totalPriceText = document.getElementById('totalPriceText');
        const inputSeats = document.getElementById('inputSeats');
        const inputTotal = document.getElementById('inputTotal');
        const btnSubmit = document.getElementById('btnSubmit');

        let selectedSeatsArr = [];
        let totalPrice = 0;

        seats.forEach(seat => {
            seat.addEventListener('click', () => {
                const seatId = seat.getAttribute('data-seat');
                const price = parseInt(seat.getAttribute('data-price'));

                if (seat.classList.contains('selected')) {
                    seat.classList.remove('selected');
                    selectedSeatsArr = selectedSeatsArr.filter(id => id !== seatId);
                    totalPrice -= price;
                } else {
                    if(selectedSeatsArr.length >= 10) {
                        alert("Bạn chỉ được chọn tối đa 10 ghế cho một lần giao dịch để chống đầu cơ vé.");
                        return;
                    }
                    seat.classList.add('selected');
                    selectedSeatsArr.push(seatId);
                    totalPrice += price;
                }
                updateSummary();
            });
        });

        function updateSummary() {
            // Sắp xếp ghế theo ABC
            selectedSeatsArr.sort();

            if (selectedSeatsArr.length > 0) {
                selectedSeatsText.textContent = selectedSeatsArr.join(', ');
                inputSeats.value = selectedSeatsArr.join(', ');
                btnSubmit.disabled = false;
            } else {
                selectedSeatsText.textContent = 'Chưa chọn ghế';
                inputSeats.value = '';
                btnSubmit.disabled = true;
            }

            const formattedPrice = new Intl.NumberFormat('vi-VN').format(totalPrice) + 'đ';
            totalPriceText.textContent = formattedPrice;
            inputTotal.value = totalPrice;
        }
    </script>
</body>
</html>