<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json'); // Báo cho trình duyệt biết đây là API trả về JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['promo_code'] ?? '');
    $current_total = (int)($_POST['current_total'] ?? 0);

    if (empty($code)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập mã giảm giá!']);
        exit;
    }

    // Kiểm tra mã trong Database
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 'active'");
    $stmt->execute([$code]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá không tồn tại hoặc đã bị khóa!']);
        exit;
    }

    // Kiểm tra ngày hết hạn
    if (strtotime($voucher['expiry_date']) < strtotime(date('Y-m-d'))) {
        echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá này đã hết hạn!']);
        exit;
    }

    // Kiểm tra số lượng lượt dùng
    if ($voucher['used_count'] >= $voucher['usage_limit']) {
        echo json_encode(['status' => 'error', 'message' => 'Mã giảm giá này đã hết lượt sử dụng!']);
        exit;
    }

    // Kiểm tra giá trị đơn hàng tối thiểu
    if ($current_total < $voucher['min_order_value']) {
        echo json_encode(['status' => 'error', 'message' => 'Đơn hàng chưa đạt giá trị tối thiểu ('.number_format($voucher['min_order_value']).'đ) để dùng mã này!']);
        exit;
    }

    // Nếu vượt qua hết các vòng kiểm tra -> Trả về số tiền được giảm
    echo json_encode([
        'status' => 'success', 
        'message' => 'Áp dụng mã giảm giá thành công!',
        'discount_amount' => $voucher['discount_amount']
    ]);
    exit;
}
?>