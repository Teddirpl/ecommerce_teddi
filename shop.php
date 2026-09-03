<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// [FIX] Pastikan session aktif sebelum dipakai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [FIX] CSRF token untuk form login & register
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 $csrf_token = $_SESSION['csrf_token'];

 $auth_error   = '';
 $auth_success = '';
 $active_form  = '';  // [FIX] penanda form mana yang error
 $old_name     = '';  // [FIX] simpan input lama agar user tidak mengetik ulang
 $old_email    = '';

// [FIX] Flash message sukses registrasi (pattern PRG: Post-Redirect-GET)
if (isset($_SESSION['shop_flash'])) {
    $flash = $_SESSION['shop_flash'];
    unset($_SESSION['shop_flash']);
    if ($flash['type'] === 'success') {
        $auth_success = $flash['text'];
        $active_form  = 'register';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shop_auth_action'])) {

    // [FIX] Verifikasi CSRF token
    if (!hash_equals($csrf_token, (string) ($_POST['csrf_token'] ?? ''))) {
        $auth_error = 'Sesi tidak valid. Silakan coba lagi.';
    } else {
        $action        = $_POST['shop_auth_action'] === 'register' ? 'register' : 'login';
        $active_form   = $action;
        $old_name      = trim((string) ($_POST['name'] ?? ''));
        $old_email     = trim((string) ($_POST['email'] ?? ''));
        $auth_password = (string) ($_POST['password'] ?? '');
        $auth_confirm  = (string) ($_POST['confirm_password'] ?? '');

        if ($action === 'register') {
            if ($old_name === '' || $old_email === '' || $auth_password === '' || $auth_confirm === '') {
                $auth_error = 'Semua kolom wajib diisi untuk daftar akun baru.';
            } elseif (!filter_var($old_email, FILTER_VALIDATE_EMAIL)) {
                $auth_error = 'Format email tidak valid.';
            } elseif (strlen($auth_password) < 8) {
                $auth_error = 'Password minimal 8 karakter.';
            } elseif ($auth_password !== $auth_confirm) {
                $auth_error = 'Konfirmasi password tidak cocok.';
            } else {
                $check = $pdo->prepare('SELECT id FROM users WHERE email = ? OR name = ? LIMIT 1');
                $check->execute([$old_email, $old_name]);

                if ($check->fetch()) {
                    $auth_error = 'Nama atau email sudah terdaftar, silakan login.';
                } else {
                    $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
                    $insert->execute([$old_name, $old_email, password_hash($auth_password, PASSWORD_DEFAULT), 'customer']);

                    // [FIX] PRG: redirect agar refresh browser tidak mengirim ulang form
                    $_SESSION['shop_flash'] = [
                        'type' => 'success',
                        'text' => 'Pendaftaran berhasil. Silakan masuk dengan akun baru Anda.',
                    ];
                    header('Location: shop.php');
                    exit;
                }
            }
        } else {
            if ($old_name === '' || $auth_password === '') {
                $auth_error = 'Nama/email dan password wajib diisi.';
            } else {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE name = ? OR email = ? LIMIT 1');
                $stmt->execute([$old_name, $old_name]);
                $user = $stmt->fetch();

                if ($user && password_verify($auth_password, $user['password'])) {
                    // [FIX] Cegah session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = (int) $user['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role']       = $user['role'];

                    header('Location: shop.php');
                    exit;
                }

                $auth_error = 'Nama/email atau password salah.';
            }
        }
    }
}

// ============================================
// 1. AMBIL & SANITASI INPUT (GET)
// ============================================
 $search   = isset($_GET['search'])   ? trim(strip_tags((string) $_GET['search'])) : '';
 $category = isset($_GET['category']) ? max(0, (int) $_GET['category'])            : 0;
 $page     = isset($_GET['page'])     ? max(1, (int) $_GET['page'])                : 1;
 $per_page = 12;

// [FIX] Helper URL: filter (search/kategori) tetap tersimpan saat pindah halaman/kategori
 $shop_url = function (int $page_num = 1, ?int $cat_override = null) use ($search, $category): string {
    $cat = $cat_override ?? $category;
    $params = [];
    if ($search !== '')   $params['search']   = $search;
    if ($cat > 0)         $params['category'] = $cat;
    if ($page_num > 1)    $params['page']     = $page_num;
    return 'shop.php' . ($params ? '?' . http_build_query($params) : '');
};

// ============================================
// 2. BUILD QUERY DINAMIS (AMAN dari SQL Injection)
// ============================================
 $sql        = "SELECT p.*, c.name AS category_name
               FROM products p
               LEFT JOIN categories c ON p.category_id = c.id
               WHERE p.stock > 0";
 $count_sql  = "SELECT COUNT(*) FROM products p WHERE p.stock > 0";
 $params       = [];
 $count_params = [];

if ($search !== '') {
    $sql           .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $count_sql     .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $keyword        = "%{$search}%";
    $params[]       = $keyword;
    $params[]       = $keyword;
    $count_params[] = $keyword;
    $count_params[] = $keyword;
}

if ($category > 0) {
    $sql           .= " AND p.category_id = ?";
    $count_sql     .= " AND p.category_id = ?";
    $params[]       = $category;
    $count_params[] = $category;
}

// ============================================
// 3. HITUNG TOTAL UNTUK PAGINATION
// ============================================
 $stmt_count = $pdo->prepare($count_sql);
 $stmt_count->execute($count_params);
 $total_products = (int) $stmt_count->fetchColumn();
 $total_pages    = max(1, (int) ceil($total_products / $per_page));

if ($page > $total_pages) $page = $total_pages;

// [FIX] Offset HARUS dihitung setelah $page di-clamp (bug: sebelumnya dihitung duluan)
 $offset = ($page - 1) * $per_page;

// ============================================
// 4. AMBIL DATA PRODUK
// ============================================
 $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
 $params[] = $per_page;
 $params[] = $offset;

 $stmt = $pdo->prepare($sql);
// [FIX] Bind tipe eksplisit: LIMIT/OFFSET butuh PDO::PARAM_INT agar aman di semua konfigurasi PDO
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
 $stmt->execute();
 $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 5. AMBIL DAFTAR KATEGORI
// ============================================
 $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);

// Helper gambar produk: cek file benar-benar ada di server
 $product_img_src = function (?string $img): string {
    return product_image_src($img);
};

 $page_title = 'Katalog Produk';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($page_title) ?> - Teddi Store</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* [FIX] Navbar fixed menutupi konten -> beri jarak dari atas.
           Sesuaikan 110px dengan tinggi navbar + topbar kamu (hapus jika navbar tidak fixed) */
        body { padding-top: 110px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container customer-page-shell">
    <section class="shop-hero animate-fade-in">
        <span class="shop-hero__badge">Fresh picks</span>
        <h1 class="shop-hero__title">Belanja kebutuhan sehari-hari dengan harga yang lebih bersaing.</h1>
        <p class="shop-hero__subtitle">Temukan produk favoritmu dengan tampilan marketplace yang lebih modern, cepat, dan nyaman untuk checkout.</p>
        <div class="shop-hero__meta">
            <span class="pill">Gratis Ongkir</span>
            <span class="pill">Promo Hari Ini</span>
            <span class="pill">Cashback 5%</span>
        </div>
    </section>

    <div class="shop-quick-promos animate-fade-in">
        <div class="promo-card promo-card--orange">
            <span class="promo-tag">Produk Baru</span>
            <h3>Flash Sale</h3>
            <p>Diskon sampai 50% untuk pilihan terbaru.</p>
        </div>
        <div class="promo-card promo-card--blue">
            <span class="promo-tag">Top Deal</span>
            <h3>Gratis Ongkir</h3>
            <p>Belanja minimal 1 item, ongkir langsung gratis.</p>
        </div>
        <div class="promo-card promo-card--green">
            <span class="promo-tag">Trending</span>
            <h3>Best Seller</h3>
            <p>Produk paling banyak dibeli minggu ini.</p>
        </div>
    </div>

    <div class="category-strip animate-fade-in">
        <a href="shop.php" class="category-item">Semua</a>
        <?php foreach (array_slice($categories, 0, 8) as $cat): ?>
            <a href="<?= htmlspecialchars($shop_url(1, (int) $cat['id'])) ?>" class="category-item"><?= sanitize($cat['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="shop-auth-panel animate-fade-in" style="margin: 1.5rem 0; padding: 1.5rem; border-radius: 18px; background: linear-gradient(135deg, rgba(238,77,45,0.06), rgba(245,158,11,0.06)); border: 1px solid rgba(238,77,45,0.18);">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
                <div style="background: rgba(255,255,255,0.8); border-radius: 16px; padding: 1.25rem; border: 1px solid rgba(148,163,184,0.18);">
                    <h3 style="margin:0 0 0.8rem; color:#0f172a;">Masuk</h3>
                    <?php if ($auth_error && $active_form === 'login'): ?>
                        <div style="margin-bottom: 0.8rem; padding: 0.7rem 0.9rem; border-radius: 10px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color:#b91c1c; font-size: 0.9rem;"><?= htmlspecialchars($auth_error) ?></div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="shop_auth_action" value="login">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div style="display:flex; flex-direction:column; gap:0.8rem;">
                            <input type="text" name="name" placeholder="Nama atau email" class="input-field" required
                                   value="<?= $active_form === 'login' ? sanitize($old_name) : '' ?>">
                            <input type="password" name="password" placeholder="Password" class="input-field" required>
                            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Masuk</button>
                        </div>
                    </form>
                </div>

                <div style="background: rgba(255,255,255,0.8); border-radius: 16px; padding: 1.25rem; border: 1px solid rgba(148,163,184,0.18);">
                    <h3 style="margin:0 0 0.8rem; color:#0f172a;">Daftar Jadi Pembeli</h3>
                    <?php if ($auth_error && $active_form === 'register'): ?>
                        <div style="margin-bottom: 0.8rem; padding: 0.7rem 0.9rem; border-radius: 10px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color:#b91c1c; font-size: 0.9rem;"><?= htmlspecialchars($auth_error) ?></div>
                    <?php endif; ?>
                    <?php if ($auth_success): ?>
                        <div style="margin-bottom: 0.8rem; padding: 0.7rem 0.9rem; border-radius: 10px; background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.25); color:#166534; font-size: 0.9rem;"><?= htmlspecialchars($auth_success) ?></div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="shop_auth_action" value="register">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div style="display:flex; flex-direction:column; gap:0.8rem;">
                            <input type="text" name="name" placeholder="Nama lengkap" class="input-field" required
                                   value="<?= $active_form === 'register' ? sanitize($old_name) : '' ?>">
                            <input type="email" name="email" placeholder="Email" class="input-field" required
                                   value="<?= $active_form === 'register' ? sanitize($old_email) : '' ?>">
                            <input type="password" name="password" placeholder="Password minimal 8 karakter" class="input-field" required>
                            <input type="password" name="confirm_password" placeholder="Ulangi password" class="input-field" required>
                            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Daftar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="GET" class="filter-bar animate-fade-in" autocomplete="off">
        <div class="filter-row">
            <input type="search" name="search"
                   value="<?= sanitize($search) ?>"
                   placeholder="Cari produk yang kamu inginkan..."
                   class="input-field">

            <select name="category" class="select-field">
                <option value="0">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"
                        <?= ($category === (int) $cat['id']) ? 'selected' : '' ?>>
                        <?= sanitize($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search !== '' || $category > 0): ?>
                <a href="shop.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($products)): // [FIX] flash sale kosong saat tidak ada produk ?>
    <div class="flash-sale-panel animate-fade-in">
        <div class="flash-sale-header">
            <div>
                <span class="flash-label">Flash Sale</span>
                <h2>Untuk kamu</h2>
            </div>
            <span class="flash-timer">Berakhir hari ini</span>
        </div>
        <div class="flash-sale-grid">
            <?php foreach (array_slice($products, 0, 4) as $p): ?>
                <a href="product_detail.php?id=<?= (int) $p['id'] ?>" class="flash-sale-item">
                    <div class="flash-sale-thumb">
                        <?php $flash_img = $product_img_src($p['image'] ?? null); ?>
                        <?php if ($flash_img !== ''): ?>
                            <img src="<?= $flash_img ?>" alt="<?= sanitize($p['name']) ?>">
                        <?php else: ?>
                            <?= render_product_icon($p['name'], $p['category_name'] ?? 'Umum', 'small') ?>
                        <?php endif; ?>
                    </div>
                    <div class="flash-sale-info">
                        <span class="flash-price">Rp <?= number_format((float) $p['price'], 0, ',', '.') ?></span>
                        <span class="flash-name"><?= sanitize($p['name']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="info-text">
        Menampilkan <strong><?= count($products) ?></strong> dari
        <strong><?= $total_products ?></strong> produk
        <?php if ($search !== ''): ?>
            untuk kata kunci "<em><?= sanitize($search) ?></em>"
        <?php endif; ?>
    </p>

    <?php if (empty($products)): ?>
        <div class="empty-box animate-fade-in">
            <h3>Produk yang kamu cari belum tersedia</h3>
            <p>Coba ubah kata kunci atau pilih kategori lain.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <?php
                $desc    = trim((string) ($p['description'] ?? ''));
                // [FIX] mb_substr: aman untuk karakter UTF-8 (tidak memotong di tengah karakter)
                $short   = mb_strlen($desc) > 90 ? mb_substr($desc, 0, 90) . '…' : $desc;
                // [FIX] angka "terjual" stabil (deterministik dari id), tidak berubah tiap refresh
                $terjual = 20 + ((int) $p['id'] * 37) % 980;
                $img     = $product_img_src($p['image'] ?? null);
                ?>
                <div class="product-card animate-fade-in">
                    <div class="product-image-container">
                        <span class="badge badge-primary product-badge"><?= sanitize($p['category_name'] ?? 'Umum') ?></span>
                        <?php if ($img !== ''): ?>
                            <img src="<?= $img ?>"
                                 alt="<?= sanitize($p['name']) ?>"
                                 class="product-image" loading="lazy" decoding="async">
                        <?php else: ?>
                            <?= render_product_icon($p['name'], $p['category_name'] ?? 'Umum', 'medium') ?>
                        <?php endif; ?>
                    </div>

                    <div class="product-info">
                        <div class="product-topline">
                            <span class="product-category"><?= sanitize($p['category_name'] ?? 'Umum') ?></span>
                            <span class="mini-sold">Terjual <?= $terjual ?></span>
                        </div>
                        <h3 class="product-name"><?= sanitize($p['name']) ?></h3>
                        <p class="product-description"><?= sanitize($short) ?></p>
                        <div class="product-footer">
                            <div>
                                <span class="product-price">Rp <?= number_format((float) $p['price'], 0, ',', '.') ?></span>
                                <?php // [FIX] "Diskon 10%" hardcoded dihapus karena menyesatkan pembeli ?>
                            </div>
                            <span class="product-stock">Stok: <?= (int) $p['stock'] ?></span>
                        </div>
                        <div class="product-actions">
                            <a href="product_detail.php?id=<?= (int) $p['id'] ?>" class="btn btn-secondary btn-block">Lihat Detail</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <?php
            // [FIX] Batasi jumlah link halaman (window + ellipsis), jangan tampilkan semua
            $range = 2;
            $start = max(1, $page - $range);
            $end   = min($total_pages, $page + $range);
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars($shop_url($page - 1)) ?>" class="pagination-btn">« Prev</a>
                <?php endif; ?>

                <?php if ($start > 1): ?>
                    <a href="<?= htmlspecialchars($shop_url(1)) ?>" class="pagination-btn">1</a>
                    <?php if ($start > 2): ?><span class="pagination-btn">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination-btn active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($shop_url($i)) ?>" class="pagination-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="pagination-btn">…</span><?php endif; ?>
                    <a href="<?= htmlspecialchars($shop_url($total_pages)) ?>" class="pagination-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= htmlspecialchars($shop_url($page + 1)) ?>" class="pagination-btn">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script src="../assets/script.js"></script>
</body>
</html>