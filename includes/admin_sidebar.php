<?php
$current_page = basename($_SERVER['PHP_SELF']);
$active_class = "flex items-center gap-3 px-4 py-3 rounded-xl bg-primary text-background-dark font-bold shadow-[0_4px_14px_0_rgba(242,204,13,0.39)]";
$normal_class = "flex items-center gap-3 px-4 py-3 rounded-xl text-slate-100 hover:bg-accent-dark transition-colors";
?>
<aside id="adminSidebar" class="w-72 bg-surface-dark border-r border-accent-dark fixed inset-y-0 left-0 z-50 transform -translate-x-full lg:relative lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col">
    <div class="p-6 flex items-center gap-3">
        <img src="images/my_logo.png" alt="Logo QHA Cinema" class="w-10 h-10 object-cover rounded-full shadow-sm border border-primary/30">
        <div>
            <h1 class="text-primary text-lg font-black leading-none uppercase tracking-wide">CineAdmin</h1>
            <p class="text-primary/60 text-[10px] font-bold uppercase tracking-widest mt-1">Hệ thống quản trị</p>
        </div>
    </div>
    
    <nav class="flex-1 px-4 space-y-2 overflow-y-auto mt-4 custom-scrollbar">
        <a class="<?php echo ($current_page == 'admin_dashboard.php') ? $active_class : $normal_class; ?>" href="admin_dashboard.php">
            <span class="material-symbols-outlined">dashboard</span><span>Dashboard</span>
        </a>
        <a class="<?php echo ($current_page == 'admin_cinemas.php') ? $active_class : $normal_class; ?>" href="admin_cinemas.php">
            <span class="material-symbols-outlined">domain</span><span>Hệ thống rạp</span>
        </a>
        <a class="<?php echo ($current_page == 'admin_movies.php') ? $active_class : $normal_class; ?>" href="admin_movies.php">
            <span class="material-symbols-outlined">movie</span><span>Quản lý phim</span>
        </a>
        <a class="<?php echo ($current_page == 'admin_showtimes.php') ? $active_class : $normal_class; ?>" href="admin_showtimes.php">
            <span class="material-symbols-outlined">calendar_month</span><span>Lịch chiếu</span>
        </a>
        <a class="<?php echo ($current_page == 'admin_users.php') ? $active_class : $normal_class; ?>" href="admin_users.php">
            <span class="material-symbols-outlined">group</span><span>Khách hàng</span>
        </a>
        
        <a class="<?php echo ($current_page == 'admin_vouchers.php') ? $active_class : $normal_class; ?>" href="admin_vouchers.php">
            <span class="material-symbols-outlined">local_activity</span><span>Mã giảm giá</span>
        </a>
        <a class="<?php echo ($current_page == 'admin_concessions.php') ? $active_class : $normal_class; ?>" href="admin_concessions.php">
            <span class="material-symbols-outlined">fastfood</span><span>Bắp nước</span>
        </a>
        
        <a class="<?php echo ($current_page == 'admin_reports.php') ? $active_class : $normal_class; ?>" href="admin_reports.php">
            <span class="material-symbols-outlined">analytics</span><span>Báo cáo doanh thu</span>
        </a>
    </nav>
    
    <div class="p-4 border-t border-accent-dark">
        <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-accent-dark text-slate-100 font-bold hover:bg-red-900/40 hover:text-red-500 transition-colors">
            <span class="material-symbols-outlined">logout</span><span>Đăng xuất</span>
        </a>
    </div>
</aside>

<style>
    aside .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    aside .custom-scrollbar::-webkit-scrollbar-thumb { background: #403a1e; border-radius: 10px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const menuBtn = document.querySelector('header .lg\\:hidden');

    if (sidebar && menuBtn) {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-black/80 z-40 hidden lg:hidden backdrop-blur-sm transition-opacity opacity-0';
        document.body.appendChild(overlay);

        function toggleMenu() {
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        menuBtn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    }
});
</script>