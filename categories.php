<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_admin();

 $error = '';
 $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } elseif (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
        $success = 'Kategori berhasil ditambahkan!';
    } elseif (isset($_POST['delete_category'])) {
        $id = (int)($_POST['category_id'] ?? 0);

        // Pastikan kategori ada
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();

        if ($category) {
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Kategori "' . htmlspecialchars($category['name']) . '" berhasil dihapus!';
            } catch (PDOException $e) {
                // Error 23000 = foreign key constraint (kategori masih dipakai produk)
                if ($e->getCode() === '23000') {
                    $error = 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.';
                } else {
                    $error = 'Terjadi kesalahan saat menghapus kategori.';
                }
            }
        } else {
            $error = 'Kategori tidak ditemukan.';
        }
    }
}

 $categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kategori - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/script.js" defer></script>
    <style>
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-danger:hover { opacity: 0.85; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="brand">Teddi Admin</div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="categories.php" class="active">Kategori</a>
                <a href="products.php">Produk</a>
                <a href="orders.php">Pesanan</a>
                <a href="reports.php">Laporan</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <h1>Manajemen Kategori</h1>
                <div class="admin-user">Halo, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
            </header>

            <?php if ($error): ?><div class="admin-alert error"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="admin-alert success"><?= $success ?></div><?php endif; ?>

            <section class="admin-panel">
                <h2>Tambah Kategori Baru</h2>
                <form method="POST" class="admin-form-grid">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <div class="admin-form-group" style="grid-column: 1 / -1;">
                        <label>Nama Kategori</label>
                        <input type="text" name="name" placeholder="Contoh: Pakaian, Elektronik" required>
                    </div>
                    <div class="admin-btn-row">
                        <button type="submit" name="add_category" class="btn btn-primary">Simpan Kategori</button>
                    </div>
                </form>
            </section>

            <section class="admin-panel">
                <h2>Daftar Kategori</h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kategori</th>
                                <th>Slug</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?= $cat['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                    <td><code style="background: rgba(148,163,184,0.1); color:#cbd5e1; padding:4px 8px; border-radius:6px;"><?= htmlspecialchars($cat['slug']) ?></code></td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Yakin ingin menghapus kategori &quot;<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>&quot;?');">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" name="delete_category" class="btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>