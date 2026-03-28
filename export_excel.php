<?php
session_start();
require_once 'includes/db_connect.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    die("Bạn không có quyền truy cập!");
}

// 1. Nhận tham số bộ lọc
$filter_month = $_GET['month'] ?? '';
$filter_cinema = $_GET['cinema_id'] ?? '';
$month_safe = preg_replace('/[^0-9-]/', '', $filter_month);
$cinema_safe = (int)$filter_cinema;

$cinema_name_display = "Tất cả hệ thống rạp";
if ($cinema_safe) {
    $stmt_c = $pdo->prepare("SELECT name FROM cinemas WHERE id = ?");
    $stmt_c->execute([$cinema_safe]);
    $cinema_name_display = $stmt_c->fetchColumn() ?: "Tất cả hệ thống rạp";
}
$month_display = $month_safe ? date('m/Y', strtotime($month_safe . '-01')) : "Tất cả thời gian";

// 2. Chuẩn bị câu lệnh SQL (Y hệt admin_reports.php)
$o_cond = "o.status = 'completed'";
if ($month_safe) $o_cond .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month_safe'";
if ($cinema_safe) $o_cond .= " AND o.movie_id IN (SELECT movie_id FROM showtimes WHERE cinema_id = $cinema_safe)";

// Lấy Tổng quan
$sql_totals = "SELECT COALESCE(SUM(total_price), 0) as total_revenue, COALESCE(SUM(IF(seat_numbers IS NULL OR seat_numbers = '', 0, LENGTH(seat_numbers) - LENGTH(REPLACE(seat_numbers, ',', '')) + 1)), 0) as total_tickets FROM orders o WHERE $o_cond";
$totals = $pdo->query($sql_totals)->fetch();
$total_revenue = $totals['total_revenue'];
$total_tickets = $totals['total_tickets'];

// Lấy Danh sách phim
$m_cond = "1=1";
if ($cinema_safe) $m_cond .= " AND m.id IN (SELECT movie_id FROM showtimes WHERE cinema_id = $cinema_safe)";
$sql_movies = "SELECT m.title, COALESCE(SUM(IF(o.seat_numbers IS NULL OR o.seat_numbers = '', 0, LENGTH(o.seat_numbers) - LENGTH(REPLACE(o.seat_numbers, ',', '')) + 1)), 0) as tickets_sold, COALESCE(SUM(o.total_price), 0) as revenue FROM movies m LEFT JOIN orders o ON m.id = o.movie_id AND $o_cond WHERE $m_cond GROUP BY m.id ORDER BY revenue DESC";
$movies_revenue = $pdo->query($sql_movies)->fetchAll();

// Lấy Danh sách rạp
$c_cond = "1=1";
if ($cinema_safe) $c_cond .= " AND c.id = $cinema_safe";
$s_join_cond = "c.id = s.cinema_id";
if ($month_safe) $s_join_cond .= " AND DATE_FORMAT(s.show_date, '%Y-%m') = '$month_safe'";
$o_join_cond = "o.movie_id = s.movie_id AND o.status = 'completed'";
if ($month_safe) $o_join_cond .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = '$month_safe'";
$sql_cinemas = "SELECT c.name, COUNT(DISTINCT s.id) as total_shows, COALESCE(SUM(IF(o.seat_numbers IS NULL OR o.seat_numbers = '', 0, LENGTH(o.seat_numbers) - LENGTH(REPLACE(o.seat_numbers, ',', '')) + 1)), 0) as tickets_sold, COALESCE(SUM(o.total_price), 0) as revenue FROM cinemas c LEFT JOIN showtimes s ON $s_join_cond LEFT JOIN orders o ON $o_join_cond WHERE $c_cond GROUP BY c.id ORDER BY revenue DESC";
$cinemas_revenue = $pdo->query($sql_cinemas)->fetchAll();

// ==========================================
// 3. XUẤT FILE EXCEL
// ==========================================
$filename = "BaoCaoDoanhThu_CineAdmin_" . date('Ymd_His') . ".xls";

// Định dạng HTTP Header để ép tải file
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Xuất HTML chuẩn để Excel có thể đọc được kèm BOM cho Unicode tiếng Việt
echo "\xEF\xBB\xBF"; 
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <h2 style="text-align: center; color: #f2cc0d;">BÁO CÁO DOANH THU - GOLD CINEMA</h2>
    <p><b>Ngày xuất báo cáo:</b> <?php echo date('d/m/Y H:i:s'); ?></p>
    <p><b>Thời gian lọc:</b> <?php echo $month_display; ?></p>
    <p><b>Cụm rạp lọc:</b> <?php echo htmlspecialchars($cinema_name_display); ?></p>
    <br>

    <h3>1. TỔNG QUAN DOANH THU</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr style="background-color: #f2cc0d; color: #000;">
            <th>Tổng Doanh Thu (VNĐ)</th>
            <th>Tổng Số Vé Đã Bán</th>
        </tr>
        <tr>
            <td><?php echo number_format($total_revenue, 0, ',', '.'); ?></td>
            <td><?php echo number_format($total_tickets); ?></td>
        </tr>
    </table>
    <br><br>

    <h3>2. CHI TIẾT THEO PHIM</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr style="background-color: #334155; color: #fff;">
            <th>STT</th>
            <th>Tên Phim</th>
            <th>Số Vé Bán Ra</th>
            <th>Doanh Thu (VNĐ)</th>
        </tr>
        <?php $stt = 1; foreach ($movies_revenue as $m): ?>
        <tr>
            <td style="text-align: center;"><?php echo $stt++; ?></td>
            <td><?php echo htmlspecialchars($m['title']); ?></td>
            <td style="text-align: center;"><?php echo number_format($m['tickets_sold']); ?></td>
            <td style="text-align: right;"><?php echo number_format($m['revenue'], 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <br><br>

    <h3>3. HIỆU SUẤT THEO CỤM RẠP</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr style="background-color: #334155; color: #fff;">
            <th>STT</th>
            <th>Tên Cụm Rạp</th>
            <th>Tổng Suất Chiếu</th>
            <th>Số Vé Bán Ra</th>
            <th>Doanh Thu (VNĐ)</th>
        </tr>
        <?php $stt = 1; foreach ($cinemas_revenue as $c): ?>
        <tr>
            <td style="text-align: center;"><?php echo $stt++; ?></td>
            <td><?php echo htmlspecialchars($c['name']); ?></td>
            <td style="text-align: center;"><?php echo number_format($c['total_shows']); ?></td>
            <td style="text-align: center;"><?php echo number_format($c['tickets_sold']); ?></td>
            <td style="text-align: right;"><?php echo number_format($c['revenue'], 0, ',', '.'); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>