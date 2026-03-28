<?php
session_start();
require_once 'includes/db_connect.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION["user_logged_in"]) || $_SESSION["role"] !== 'admin') {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. TỔNG QUAN 4 THẺ (CARDS)
// ==========================================
$sql_totals = "
    SELECT 
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(SUM(IF(seat_numbers IS NULL OR seat_numbers = '', 0, LENGTH(seat_numbers) - LENGTH(REPLACE(seat_numbers, ',', '')) + 1)), 0) as total_tickets
    FROM orders 
    WHERE status = 'completed'
";
$totals = $pdo->query($sql_totals)->fetch();
$total_revenue = $totals['total_revenue'];
$total_tickets = $totals['total_tickets'];

$active_movies = $pdo->query("SELECT COUNT(*) FROM movies WHERE status = 'now_showing'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();

// ==========================================
// 2. PHIM ĂN KHÁCH NHẤT (Top 4)
// ==========================================
$stmt_top_movies = $pdo->query("
    SELECT m.title, m.poster_url,
           COALESCE(SUM(IF(o.seat_numbers IS NULL OR o.seat_numbers = '', 0, LENGTH(o.seat_numbers) - LENGTH(REPLACE(o.seat_numbers, ',', '')) + 1)), 0) as tickets_sold
    FROM movies m
    JOIN orders o ON m.id = o.movie_id AND o.status = 'completed'
    GROUP BY m.id
    ORDER BY tickets_sold DESC
    LIMIT 4
");
$top_movies = $stmt_top_movies->fetchAll();
$max_tickets = (count($top_movies) > 0 && $top_movies[0]['tickets_sold'] > 0) ? $top_movies[0]['tickets_sold'] : 1;

// ==========================================
// 3. XU HƯỚNG DOANH THU (CÓ BỘ LỌC)
// ==========================================
// Nhận giá trị lọc, mặc định là 7 ngày
$trend_days = isset($_GET['trend']) ? (int)$_GET['trend'] : 7;
if (!in_array($trend_days, [7, 30])) $trend_days = 7;
$interval = $trend_days - 1;

$chart_labels = [];
$chart_data_assoc = [];

for ($i = $interval; $i >= 0; $i--) {
    $date_key = date('Y-m-d', strtotime("-$i days"));
    $date_label = date('d/m', strtotime("-$i days"));
    $chart_labels[] = $date_label;
    $chart_data_assoc[$date_key] = 0;
}

$stmt_trend = $pdo->prepare("
    SELECT DATE(created_at) as order_date, SUM(total_price) as daily_revenue
    FROM orders
    WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(created_at)
");
$stmt_trend->execute([$interval]);

while ($row = $stmt_trend->fetch()) {
    $date = $row['order_date'];
    if (isset($chart_data_assoc[$date])) {
        $chart_data_assoc[$date] = (int)$row['daily_revenue'];
    }
}
$chart_data_values = array_values($chart_data_assoc);

// ==========================================
// 4. GIAO DỊCH GẦN ĐÂY (Top 6)
// ==========================================
$stmt_recent_orders = $pdo->query("
    SELECT o.id, o.total_price, o.status, o.created_at, 
           m.title as movie_title, 
           u.fullname as user_name
    FROM orders o
    JOIN movies m ON o.movie_id = m.id
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 6
");
$recent_orders = $stmt_recent_orders->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Dashboard - CineAdmin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script id="tailwind-config">
        tailwind.config = { darkMode: "class", theme: { extend: { colors: { "primary": "#f2cc0d", "background-light": "#f8f8f5", "background-dark": "#1a180b", "surface-dark": "#2a2614", "accent-dark": "#403a1e", }, fontFamily: {"display": ["Be Vietnam Pro"]} } } }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    
    <?php require_once 'includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-16 bg-surface-dark/50 backdrop-blur-md border-b border-accent-dark px-8 flex items-center justify-between z-10 sticky top-0">
            <div class="flex items-center gap-4 flex-1">
                <div class="lg:hidden text-primary cursor-pointer"><span class="material-symbols-outlined">menu</span></div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-slate-100 leading-none"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-[10px] text-primary font-medium uppercase mt-1">Quản trị viên</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-accent-dark border-2 border-primary/30 flex items-center justify-center text-primary font-bold">
                    <?php echo substr(trim($_SESSION['user_name']), 0, 1); ?>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            
            <div class="mb-8 border-b border-accent-dark pb-6">
                <h1 class="text-3xl font-black text-slate-100 tracking-tight">Chào mừng trở lại, <?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name']))[count(explode(' ', trim($_SESSION['user_name']))) - 1]); ?>!</h1>
                <p class="text-slate-400 mt-1">Dưới đây là tổng quan hiệu suất của rạp ngày hôm nay.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden group">
                    <span class="material-symbols-outlined text-primary mb-4 block opacity-80">account_balance_wallet</span>
                    <p class="text-slate-400 text-[11px] font-bold uppercase tracking-widest">Tổng doanh thu</p>
                    <div class="flex items-baseline gap-1 mt-1">
                        <h3 class="text-3xl font-black text-slate-100"><?php echo number_format($total_revenue, 0, ',', '.'); ?></h3>
                        <span class="text-slate-500 text-sm font-bold">VNĐ</span>
                    </div>
                </div>

                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden">
                    <span class="material-symbols-outlined text-primary mb-4 block opacity-80">local_activity</span>
                    <p class="text-slate-400 text-[11px] font-bold uppercase tracking-widest">Tổng vé đã bán</p>
                    <h3 class="text-3xl font-black text-slate-100 mt-1"><?php echo number_format($total_tickets); ?></h3>
                </div>

                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden">
                    <span class="material-symbols-outlined text-primary mb-4 block opacity-80">movie</span>
                    <p class="text-slate-400 text-[11px] font-bold uppercase tracking-widest">Phim đang chiếu</p>
                    <h3 class="text-3xl font-black text-slate-100 mt-1"><?php echo $active_movies; ?></h3>
                </div>

                <div class="bg-surface-dark border border-accent-dark p-6 rounded-2xl shadow-lg relative overflow-hidden">
                    <span class="material-symbols-outlined text-primary mb-4 block opacity-80">group</span>
                    <p class="text-slate-400 text-[11px] font-bold uppercase tracking-widest">Tổng người dùng</p>
                    <h3 class="text-3xl font-black text-slate-100 mt-1"><?php echo $total_users; ?></h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                
                <div class="lg:col-span-2 bg-surface-dark border border-accent-dark rounded-2xl p-6 shadow-xl flex flex-col">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-slate-100">Xu hướng doanh thu</h3>
                            <p class="text-sm text-slate-500 mt-1">Thống kê <?php echo $trend_days; ?> ngày gần nhất</p>
                        </div>
                        <form method="GET" action="admin_dashboard.php" class="relative">
                            <select name="trend" onchange="this.form.submit()" class="bg-background-dark border border-accent-dark hover:border-primary/50 rounded-lg py-2 pl-4 pr-10 text-xs font-bold text-slate-200 focus:border-primary outline-none appearance-none cursor-pointer transition-all">
                                <option value="7" <?php echo $trend_days == 7 ? 'selected' : ''; ?>>1 Tuần qua</option>
                                <option value="30" <?php echo $trend_days == 30 ? 'selected' : ''; ?>>1 Tháng qua</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400 text-[18px]">expand_more</span>
                        </form>
                    </div>
                    <div class="flex-1 w-full relative min-h-[300px]">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="lg:col-span-1 bg-surface-dark border border-accent-dark rounded-2xl p-6 shadow-xl flex flex-col">
                    <h3 class="text-xl font-bold text-slate-100 mb-6">Phim ăn khách nhất</h3>
                    
                    <div class="space-y-6 flex-1 overflow-y-auto custom-scrollbar pr-2">
                        <?php foreach ($top_movies as $movie): 
                            $percent = ($movie['tickets_sold'] / $max_tickets) * 100;
                        ?>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-16 rounded-md bg-slate-800 bg-cover bg-center shrink-0 border border-accent-dark shadow-md" style="background-image: url('<?php echo htmlspecialchars($movie['poster_url']); ?>');"></div>
                            
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-bold text-slate-100 truncate" title="<?php echo htmlspecialchars($movie['title']); ?>"><?php echo htmlspecialchars($movie['title']); ?></h4>
                                <p class="text-xs text-slate-500 mb-2"><?php echo $movie['tickets_sold']; ?> vé đã bán</p>
                                
                                <div class="w-full bg-background-dark h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-primary h-full rounded-full" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (count($top_movies) == 0): ?>
                            <div class="text-center py-10 text-slate-500">
                                <span class="material-symbols-outlined text-3xl mb-2">movie</span>
                                <p class="text-sm">Chưa có dữ liệu bán vé.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-surface-dark border border-accent-dark rounded-2xl p-6 shadow-xl flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-slate-100">Giao dịch gần đây</h3>
                        <p class="text-sm text-slate-500 mt-1">Các vé xem phim vừa được khách hàng đặt thành công.</p>
                    </div>
                </div>
                
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="border-b border-accent-dark text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="py-4 pr-4 pl-2">Mã Đơn</th>
                                <th class="py-4 pr-4">Khách Hàng</th>
                                <th class="py-4 pr-4">Tên Phim</th>
                                <th class="py-4 pr-4 text-right">Tổng Tiền</th>
                                <th class="py-4 pr-4 text-center">Thời Gian</th>
                                <th class="py-4 text-right pr-2">Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-dark/50">
                            <?php foreach($recent_orders as $order): 
                                $order_code = "CGV" . str_pad($order['id'], 8, "0", STR_PAD_LEFT);
                            ?>
                            <tr class="hover:bg-accent-dark/20 transition-colors group">
                                <td class="py-4 pr-4 pl-2 text-sm font-mono text-slate-300">#<?php echo $order_code; ?></td>
                                <td class="py-4 pr-4">
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-full bg-accent-dark flex items-center justify-center text-primary font-bold border border-primary/20 shrink-0 text-xs">
                                            <?php echo mb_substr(trim($order['user_name']), 0, 1, "UTF-8"); ?>
                                        </div>
                                        <span class="text-sm font-bold text-slate-100"><?php echo htmlspecialchars($order['user_name']); ?></span>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 text-sm font-medium text-slate-300 truncate max-w-[200px]" title="<?php echo htmlspecialchars($order['movie_title']); ?>">
                                    <?php echo htmlspecialchars($order['movie_title']); ?>
                                </td>
                                <td class="py-4 pr-4 text-sm font-black text-primary text-right"><?php echo number_format($order['total_price'], 0, ',', '.'); ?>đ</td>
                                <td class="py-4 pr-4 text-xs font-medium text-slate-500 text-center"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                <td class="py-4 text-right pr-2">
                                    <?php if($order['status'] == 'completed'): ?>
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-widest bg-green-500/10 text-green-500 border border-green-500/20 inline-block">Thành công</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-500 border border-amber-500/20 inline-block">Đang xử lý</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(count($recent_orders) == 0): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 text-sm">Chưa có giao dịch nào được thực hiện.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Dữ liệu từ PHP
    const labels = <?php echo json_encode($chart_labels); ?>;
    const dataValues = <?php echo json_encode($chart_data_values); ?>;

    const maxValue = Math.max(...dataValues);
    const backgroundColors = dataValues.map(val => 
        (val === maxValue && val > 0) ? '#f2cc0d' : 'rgba(242, 204, 13, 0.2)'
    );
    const hoverColors = dataValues.map(val => '#f2cc0d'); 

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: dataValues,
                backgroundColor: backgroundColors,
                hoverBackgroundColor: hoverColors,
                borderRadius: 4,
                borderSkipped: false,
                barThickness: 'flex',
                maxBarThickness: 40 
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a180b',
                    titleColor: '#f2cc0d',
                    bodyColor: '#ffffff',
                    borderColor: '#403a1e',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw || 0;
                            return new Intl.NumberFormat('vi-VN').format(value) + ' VNĐ';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#2a2614', drawBorder: false },
                    ticks: {
                        color: '#64748b',
                        font: { family: "'Be Vietnam Pro', sans-serif" },
                        callback: function(value) {
                            if (value >= 1000000) return value / 1000000 + 'M';
                            if (value >= 1000) return value / 1000 + 'K';
                            return value;
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: '#64748b', font: { family: "'Be Vietnam Pro', sans-serif" } }
                }
            }
        }
    });
</script>
</body>
</html>