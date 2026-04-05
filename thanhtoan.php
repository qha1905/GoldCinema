<?php
session_start();
require_once 'includes/db_connect.php';

// Yêu cầu phải có dữ liệu POST từ trang chọn ghế truyền sang
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_seats'])) {
    header("Location: index.php");
    exit;
}

// 1. Lấy dữ liệu từ form chọn ghế
$movie_id = $_POST['movie_id'] ?? 0;
// ĐÃ THÊM: Hứng ID rạp từ trang chọn ghế
$cinema_id = $_POST['cinema_id'] ?? 0; 
$show_time = $_POST['show_time'] ?? '';
$room_name = $_POST['room_name'] ?? '';
$selected_seats = $_POST['selected_seats'] ?? '';
$ticket_price = (int)($_POST['total_price'] ?? 0);

// 2. Lấy thông tin phim
$stmt = $pdo->prepare("SELECT title, poster_url FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();

if (!$movie) {
    die("Lỗi: Không tìm thấy thông tin phim.");
}

// 3. Cấu hình thông tin API VietQR
$bank_id = "TCB";                 // Mã ngân hàng
$account_no = "393944441111";      // Số tài khoản của bạn
$account_name = "NGUYEN VAN A";  // Tên chủ thẻ 
$transfer_content = "Mua vé " . $movie['title'] . " - " . time(); // Nội dung chuyển khoản

// 4. Tính toán
$seats_array = explode(',', $selected_seats);
$seat_count = count($seats_array);
$convenience_fee = 10000; // Phí tiện ích cố định 10.000đ
$total_price = $ticket_price + $convenience_fee;

?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Thanh toán - Gold Cinema</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180a", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; background-color: #1a180a; }
        input[type="radio"] { accent-color: #f2cc0d; width: 1.2rem; height: 1.2rem; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col items-center py-10">

    <main class="w-full max-w-5xl mx-auto px-6">
        
        <div class="flex items-center gap-3 mb-8 border-b border-accent-dark pb-6">
            <a href="javascript:history.back()" class="flex items-center text-primary hover:text-white transition-colors">
                <span class="material-symbols-outlined text-2xl">arrow_back_ios</span>
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-white">Thanh toán</h1>
        </div>

        <form action="xacnhanthanhcong.php" method="POST" id="paymentForm" class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <div class="lg:col-span-2 space-y-8">
                
                <div>
                    <h2 class="text-lg font-bold text-primary flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined">account_balance_wallet</span> Phương thức thanh toán
                    </h2>
                    
                    <div class="space-y-4">
                        <label class="block cursor-pointer">
                            <div class="bg-surface-dark border border-accent-dark hover:border-primary/50 rounded-xl p-4 flex items-center justify-between transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-white p-1 flex items-center justify-center overflow-hidden">
                                        <span class="material-symbols-outlined text-primary text-3xl">account_balance</span>
                                    </div>
                                    <span class="font-bold text-slate-200">Chuyển khoản Ngân hàng (VietQR)</span>
                                </div>
                                <input type="radio" name="payment_method" value="Chuyển khoản VietQR" class="cursor-pointer" checked onchange="toggleQR()">
                            </div>
                        </label>

                        <div id="vietQrBox" class="bg-surface-dark border border-accent-dark rounded-xl p-6 flex flex-col items-center justify-center gap-4 transition-all overflow-hidden mt-4">
                            <p class="text-sm text-slate-400 font-medium text-center">Mở ứng dụng Ngân hàng và quét mã QR dưới đây.<br>Hệ thống tự động điền <strong class="text-primary">Số tiền</strong> và <strong class="text-primary">Nội dung</strong>.</p>
                            
                            <div class="bg-white p-2 rounded-xl shadow-[0_0_20px_rgba(242,204,13,0.15)] relative w-64 h-64 flex items-center justify-center overflow-hidden">
                                <img id="vietqrImage" src="https://img.vietqr.io/image/<?php echo $bank_id; ?>-<?php echo $account_no; ?>-compact2.png?amount=<?php echo $total_price; ?>&addInfo=<?php echo $transfer_content; ?>&accountName=<?php echo urlencode($account_name); ?>" alt="Mã QR VietQR" class="w-full h-full object-contain transition-opacity duration-300">
                            </div>

                            <div class="w-full max-w-sm bg-background-dark border border-accent-dark rounded-lg p-4 mt-2">
                                <p class="text-xs text-slate-500 mb-1 uppercase tracking-widest font-bold text-center">Nội dung chuyển khoản</p>
                                <div class="flex items-center justify-center gap-2">
                                    <p class="text-xl font-mono font-black text-primary tracking-wider"><?php echo $transfer_content; ?></p>
                                </div>
                            </div>
                        </div>

                        <label class="block cursor-pointer">
                            <div class="bg-surface-dark border border-accent-dark hover:border-primary/50 rounded-xl p-4 flex items-center justify-between transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-white p-1 flex items-center justify-center">
                                        <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-ZaloPay-Square.png" alt="ZaloPay" class="w-full rounded object-contain">
                                    </div>
                                    <span class="font-bold text-slate-200">ZaloPay</span>
                                </div>
                                <input type="radio" name="payment_method" value="ZaloPay" class="cursor-pointer" onchange="toggleQR()">
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <h2 class="text-lg font-bold text-primary flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined">local_activity</span> Mã giảm giá / Quà tặng
                    </h2>
                    <div class="bg-surface-dark border border-accent-dark rounded-xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-slate-500">sell</span>
                        <input type="text" id="promoCode" placeholder="Nhập mã giảm giá..." class="flex-1 bg-transparent border-none text-slate-200 placeholder:text-slate-500 focus:ring-0 uppercase font-medium">
                        <button type="button" onclick="applyPromo()" class="bg-accent-dark hover:bg-primary hover:text-background-dark text-slate-300 font-bold px-4 py-2 rounded-lg transition-colors">Áp dụng</button>
                    </div>
                    <p id="promoMessage" class="text-sm mt-2 hidden"></p>
                </div>
            </div>

            <div class="lg:col-span-1 relative">
                <div class="sticky top-10 lg:mt-11">
                    <div class="bg-surface-dark border border-accent-dark rounded-2xl p-6 shadow-2xl">
                        <h3 class="text-lg font-bold text-slate-100 mb-6 border-b border-accent-dark pb-4">Tóm tắt đơn hàng</h3>
                        
                        <div class="flex items-start gap-4 mb-6">
                            <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster" class="w-20 rounded-md shadow-md border border-accent-dark">
                            <div>
                                <h4 class="text-lg font-bold text-primary mb-1"><?php echo htmlspecialchars($movie['title']); ?></h4>
                                <p class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($show_time); ?></p>
                                <p class="text-xs text-slate-400 mt-1">Phòng: <span class="text-slate-200"><?php echo htmlspecialchars($room_name); ?></span></p>
                                <p class="text-xs text-slate-400">Ghế: <span class="text-primary font-bold"><?php echo htmlspecialchars($selected_seats); ?></span></p>
                            </div>
                        </div>

                        <div class="border-t border-dashed border-accent-dark pt-4 mb-4 space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-300">Giá vé (<?php echo $seat_count; ?>x)</span>
                                <span class="font-bold text-slate-100"><?php echo number_format($ticket_price, 0, ',', '.'); ?>đ</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-300">Phí tiện ích</span>
                                <span class="font-bold text-slate-100"><?php echo number_format($convenience_fee, 0, ',', '.'); ?>đ</span>
                            </div>
                            <div id="discountRow" class="flex justify-between items-center text-sm hidden">
                                <span class="text-green-500">Giảm giá</span>
                                <span id="discountValue" class="font-bold text-green-500">-0đ</span>
                            </div>
                        </div>

                        <div class="border-t border-accent-dark pt-4 mb-8">
                            <div class="flex justify-between items-end">
                                <span class="text-slate-400 font-bold uppercase tracking-wider text-sm">Tổng tiền</span>
                                <span id="finalPriceText" class="text-2xl font-black text-primary"><?php echo number_format($total_price, 0, ',', '.'); ?>đ</span>
                            </div>
                        </div>

                        <input type="hidden" name="cinema_id" value="<?php echo htmlspecialchars($cinema_id); ?>">
                        
                        <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie_id); ?>">
                        <input type="hidden" name="show_time" value="<?php echo htmlspecialchars($show_time); ?>">
                        <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room_name); ?>">
                        <input type="hidden" name="selected_seats" value="<?php echo htmlspecialchars($selected_seats); ?>">
                        <input type="hidden" name="total_price" id="inputFinalTotal" value="<?php echo htmlspecialchars($total_price); ?>">
                        <input type="hidden" name="applied_promo_code" id="inputAppliedPromo" value="">

                        <button type="submit" class="w-full bg-primary text-background-dark py-4 rounded-xl font-bold text-lg hover:bg-primary/90 transition-all shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]">
                            Xác nhận thanh toán
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </main>

    <script>
        const originalTotal = <?php echo $total_price; ?>;
        let currentTotal = originalTotal;
        let isPromoApplied = false;

        // Dữ liệu API VietQR
        const bankId = "<?php echo $bank_id; ?>";
        const accNo = "<?php echo $account_no; ?>";
        const accName = "<?php echo urlencode($account_name); ?>";
        const addInfo = "<?php echo $transfer_content; ?>";

        // Xử lý Ẩn/Hiện Box QR
        function toggleQR() {
            const qrRadio = document.querySelector('input[name="payment_method"][value="Chuyển khoản VietQR"]');
            const qrBox = document.getElementById('vietQrBox');
            if (qrRadio && qrRadio.checked) {
                qrBox.style.display = 'flex';
            } else {
                qrBox.style.display = 'none';
            }
        }

        // Cập nhật giá lên giao diện và TỰ ĐỘNG CẬP NHẬT MÃ QR
        function updateDisplayPrice() {
            const formattedPrice = new Intl.NumberFormat('vi-VN').format(currentTotal) + 'đ';
            document.getElementById('finalPriceText').textContent = formattedPrice;
            document.getElementById('inputFinalTotal').value = currentTotal;

            // Cập nhật lại ảnh QR Code với số tiền mới
            const qrImage = document.getElementById('vietqrImage');
            qrImage.style.opacity = '0.5'; 
            const newQrUrl = `https://img.vietqr.io/image/${bankId}-${accNo}-compact2.png?amount=${currentTotal}&addInfo=${addInfo}&accountName=${accName}`;
            
            qrImage.src = newQrUrl;
            qrImage.onload = () => { qrImage.style.opacity = '1'; }
        }

        // Xử lý áp dụng mã giảm giá bằng AJAX (Fetch API)
        async function applyPromo() {
            const inputCode = document.getElementById('promoCode').value.trim().toUpperCase();
            const msgObj = document.getElementById('promoMessage');
            const discountRow = document.getElementById('discountRow');
            const discountValue = document.getElementById('discountValue');

            msgObj.classList.remove('hidden');

            if (isPromoApplied) {
                msgObj.textContent = "Bạn đã áp dụng mã giảm giá rồi!";
                msgObj.className = "text-sm mt-2 text-amber-500";
                return;
            }

            if (inputCode === '') {
                msgObj.textContent = "Vui lòng nhập mã giảm giá.";
                msgObj.className = "text-sm mt-2 text-red-500";
                return;
            }

            // Giao diện: Đang xử lý
            msgObj.textContent = "Đang kiểm tra mã...";
            msgObj.className = "text-sm mt-2 text-slate-400";

            try {
                // Đóng gói dữ liệu gửi lên API
                let formData = new FormData();
                formData.append('promo_code', inputCode);
                formData.append('current_total', originalTotal); // Gửi tổng tiền gốc lên kiểm tra

                // Gửi request bằng Fetch API
                const response = await fetch('check_voucher.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.status === 'success') {
                    // Xử lý khi mã hợp lệ
                    let discountAmt = parseInt(result.discount_amount);
                    if (currentTotal < discountAmt) discountAmt = currentTotal; // Không để âm tiền

                    currentTotal -= discountAmt;
                    isPromoApplied = true;

                    // THÊM DÒNG NÀY VÀO ĐÂY: Lưu tên mã giảm giá vào form để gửi đi
                    document.getElementById('inputAppliedPromo').value = inputCode;

                    msgObj.textContent = result.message;
                    msgObj.className = "text-sm mt-2 text-green-500 font-bold";
                    document.getElementById('promoCode').disabled = true;
                    
                    discountRow.classList.remove('hidden');
                    discountValue.textContent = "-" + new Intl.NumberFormat('vi-VN').format(discountAmt) + 'đ';

                    updateDisplayPrice(); // Cập nhật lại giao diện và QR Code
                } else {
                    // Xử lý khi mã lỗi (hết hạn, sai mã...)
                    msgObj.textContent = result.message;
                    msgObj.className = "text-sm mt-2 text-red-500";
                }
            } catch (error) {
                console.error("Lỗi AJAX:", error);
                msgObj.textContent = "Lỗi kết nối máy chủ, vui lòng thử lại!";
                msgObj.className = "text-sm mt-2 text-red-500";
            }
        }
    </script>
</body>
</html>