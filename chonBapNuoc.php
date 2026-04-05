<?php
session_start();
require_once 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_seats'])) {
    header("Location: index.php");
    exit;
}

$movie_id = $_POST['movie_id'];
$cinema_id = $_POST['cinema_id'];
$show_time = $_POST['show_time'];
$room_name = $_POST['room_name'];
$selected_seats = $_POST['selected_seats'];
$ticket_price = (int)$_POST['total_price'];

$stmt = $pdo->prepare("SELECT title, poster_url FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();

$concessions = $pdo->query("SELECT * FROM concessions WHERE status = 'active'")->fetchAll();
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>Chọn Bắp Nước - Gold Cinema</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"primary":"#f2cc0d","background-dark":"#1a180a","surface-dark":"#2a2614","accent-dark":"#403a1e"}}}}</script>
    <style>body{font-family:"Be Vietnam Pro",sans-serif;background-color:#1a180a;}</style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col items-center py-10">
    <main class="w-full max-w-[1200px] mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-8">
            <div class="flex items-center gap-3 mb-8 border-b border-accent-dark pb-6">
                <a href="javascript:history.back()" class="text-primary hover:text-white"><span class="material-symbols-outlined text-2xl">arrow_back_ios</span></a>
                <h1 class="text-2xl font-bold tracking-tight">Chọn Bắp Nước (F&B)</h1>
            </div>
            
            <div class="space-y-4">
                <?php foreach($concessions as $item): ?>
                <div class="bg-surface-dark border border-accent-dark rounded-2xl p-4 flex items-center gap-6">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="w-24 h-24 object-contain bg-white/5 rounded-xl p-2">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-slate-100"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="text-sm text-slate-400 mb-2"><?php echo htmlspecialchars($item['description']); ?></p>
                        <p class="text-primary font-bold"><?php echo number_format($item['price'], 0, ',', '.'); ?>đ</p>
                    </div>
                    <div class="flex items-center gap-4 bg-background-dark border border-accent-dark rounded-xl p-1.5">
                        <button onclick="updateQty(<?php echo $item['id']; ?>, '<?php echo $item['name']; ?>', <?php echo $item['price']; ?>, -1)" class="w-8 h-8 rounded-lg bg-surface-dark text-slate-300 hover:text-primary flex items-center justify-center font-bold">-</button>
                        <span id="qty_<?php echo $item['id']; ?>" class="w-6 text-center font-bold text-lg">0</span>
                        <button onclick="updateQty(<?php echo $item['id']; ?>, '<?php echo $item['name']; ?>', <?php echo $item['price']; ?>, 1)" class="w-8 h-8 rounded-lg bg-primary text-background-dark flex items-center justify-center font-bold">+</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="lg:col-span-4">
            <form action="thanhtoan.php" method="POST" class="bg-surface-dark border border-accent-dark rounded-2xl p-6 sticky top-10 shadow-2xl">
                <h3 class="text-lg font-bold text-slate-100 mb-6 border-b border-accent-dark pb-4">Tóm tắt đơn hàng</h3>
                <div class="flex items-start gap-4 mb-6">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster" class="w-16 rounded-md shadow-md border border-accent-dark">
                    <div>
                        <h4 class="font-bold text-primary mb-1"><?php echo htmlspecialchars($movie['title']); ?></h4>
                        <p class="text-xs text-slate-400">Phòng: <?php echo htmlspecialchars($room_name); ?></p>
                        <p class="text-xs text-slate-400">Ghế: <span class="text-primary font-bold"><?php echo htmlspecialchars($selected_seats); ?></span></p>
                    </div>
                </div>

                <div class="space-y-3 border-t border-dashed border-accent-dark pt-4 mb-4 text-sm">
                    <div class="flex justify-between"><span class="text-slate-300">Tiền vé</span><span class="font-bold"><?php echo number_format($ticket_price, 0, ',', '.'); ?>đ</span></div>
                    <div class="flex justify-between"><span class="text-slate-300">Bắp nước</span><span id="fbPriceText" class="font-bold">0đ</span></div>
                </div>
                <p id="concessionsText" class="text-xs text-primary/80 italic mb-6">Chưa chọn bắp nước</p>

                <div class="flex justify-between items-end border-t border-accent-dark pt-4 mb-6">
                    <span class="text-slate-400 font-bold uppercase tracking-wider text-sm">Tổng cộng</span>
                    <span id="finalPriceText" class="text-2xl font-black text-primary"><?php echo number_format($ticket_price, 0, ',', '.'); ?>đ</span>
                </div>

                <input type="hidden" name="movie_id" value="<?php echo $movie_id; ?>">
                <input type="hidden" name="cinema_id" value="<?php echo $cinema_id; ?>">
                <input type="hidden" name="show_time" value="<?php echo htmlspecialchars($show_time); ?>">
                <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room_name); ?>">
                <input type="hidden" name="selected_seats" value="<?php echo htmlspecialchars($selected_seats); ?>">
                <input type="hidden" name="total_price" value="<?php echo $ticket_price; ?>"> <input type="hidden" name="concessions_data" id="inputConcessionsData" value="">
                <input type="hidden" name="concessions_price" id="inputConcessionsPrice" value="0">

                <button type="submit" class="w-full bg-primary text-background-dark py-4 rounded-xl font-bold text-lg hover:bg-primary/90 transition-all">Tiếp tục thanh toán</button>
            </form>
        </div>
    </main>

    <script>
        const ticketPrice = <?php echo $ticket_price; ?>;
        let concessions = {};

        function updateQty(id, name, price, change) {
            if (!concessions[id]) concessions[id] = {name: name, price: price, qty: 0};
            concessions[id].qty += change;
            if (concessions[id].qty < 0) concessions[id].qty = 0;
            document.getElementById('qty_' + id).innerText = concessions[id].qty;
            updateCart();
        }

        function updateCart() {
            let textArr = []; let fbPrice = 0;
            for (let id in concessions) {
                if (concessions[id].qty > 0) {
                    textArr.push(concessions[id].qty + 'x ' + concessions[id].name);
                    fbPrice += concessions[id].qty * concessions[id].price;
                }
            }
            document.getElementById('concessionsText').innerText = textArr.length > 0 ? textArr.join(' + ') : 'Chưa chọn bắp nước';
            document.getElementById('fbPriceText').innerText = new Intl.NumberFormat('vi-VN').format(fbPrice) + 'đ';
            document.getElementById('finalPriceText').innerText = new Intl.NumberFormat('vi-VN').format(ticketPrice + fbPrice) + 'đ';
            
            document.getElementById('inputConcessionsData').value = textArr.join(' + ');
            document.getElementById('inputConcessionsPrice').value = fbPrice;
        }
    </script>
</body>
</html>