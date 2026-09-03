<?php
$nav_user = $_SESSION['user_name'] ?? 'Pengunjung';
$nav_role = $_SESSION['role'] ?? null;
$is_logged_in = isset($_SESSION['user_id']);
?>
<nav class="market-navbar">
    <div class="market-topbar">
        <div class="container market-topbar__inner">
            <div class="market-topbar__links">
                <span>Seller Centre</span>
                <span>Mulai Berjualan</span>
                <span>Download</span>
                <span>Ikuti kami</span>
            </div>
            <div class="market-topbar__links market-topbar__links--right">
                <span>Notifikasi</span>
                <span>Bantuan</span>
                <?php if ($is_logged_in): ?>
                    <span>Halo, <?= htmlspecialchars($nav_user) ?></span>
                    <a href="../logout.php">Logout</a>
                <?php else: ?>
                    <a href="../register.php">Daftar</a>
                    <a href="../login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="market-mainbar">
        <div class="container market-mainbar__inner">
            <a href="shop.php" class="market-brand">
                <span class="market-brand__icon">S</span>
                <span class="market-brand__text">Teddi Shop</span>
            </a>

            <div class="market-search">
                <input type="text" value="" placeholder="Cari produk favoritmu..." aria-label="Cari produk">
                <button type="button">Cari</button>
            </div>

            <div class="market-actions">
                <a href="cart.php" class="market-cart" aria-label="Keranjang">
                    🛒
                    <?php if ($is_logged_in): ?><span class="market-cart__count">0</span><?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</nav>
