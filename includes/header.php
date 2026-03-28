<?php
// File: includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

$isLoggedIn = isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"] === true;
?>
<!DOCTYPE html>
<html class="dark" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>GOLD CINEMA</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@100;300;400;500;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2cc0d",
                        "background-light": "#f8f8f5",
                        "background-dark": "#221f10",
                    },
                    fontFamily: {
                        "display": ["Be Vietnam Pro", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        body { font-family: "Be Vietnam Pro", sans-serif; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased font-display">
    <div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
        
        <header class="sticky top-0 z-50 w-full border-b border-white/10 bg-background-dark/80 backdrop-blur-md px-6 py-3">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <a href="index.php" class="flex items-center gap-2 text-primary">
                    <span class="material-symbols-outlined text-3xl">movie</span>
                    <h2 class="text-xl font-bold tracking-tight">GOLD CINEMA</h2>
                </a>
                
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
                    <a class="hover:text-primary transition-colors" href="#">Lịch Chiếu</a>
                    <a class="hover:text-primary transition-colors" href="#">Phim Đang Chiếu</a>
                    <a class="hover:text-primary transition-colors" href="#">Rạp</a>
                    <a class="hover:text-primary transition-colors" href="#">Ưu Đãi</a>
                </nav>

                <div class="flex items-center gap-4">
                    <button class="flex items-center justify-center rounded-lg h-10 w-10 bg-primary/10 text-primary hover:bg-primary/20 transition-all">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="flex items-center gap-3 group relative cursor-pointer">
                            <div class="hidden sm:flex flex-col items-end">
                                <span class="text-slate-100 font-bold text-sm"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                <span class="text-[10px] text-primary font-bold uppercase">Hạng Vàng</span>
                            </div>
                            <div class="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center border border-primary/30 overflow-hidden">
                                <span class="material-symbols-outlined text-primary">person</span>
                            </div>
                            
                            <div class="absolute right-0 top-full mt-2 w-40 bg-background-dark border border-primary/20 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
                                <a href="logout.php" class="block px-4 py-3 text-sm text-white hover:bg-primary/20 hover:text-primary rounded-lg">
                                    Đăng xuất
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="bg-primary text-background-dark text-sm font-bold px-4 py-2 rounded-lg hover:brightness-110">Đăng nhập</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="flex-1"></main>