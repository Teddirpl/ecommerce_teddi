<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_admin();

if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status   = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
}

$orders = $pdo->query("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/script.js" defer></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="brand">Teddi Admin</div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="categories.php">Kategori</a>
                <a href="products.php">Produk</a>
                <a href="orders.php" class="active">Pesanan</a>
                <a href="reports.php">Laporan</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <h1>Daftar Pesanan</h1>
                <div class="admin-user">Halo, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
            </header>

            <section class="admin-panel">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Pelanggan</th>
                                <th>Total Biaya</th>
                                <th>Alamat</th>
                                <th>Status</th>
                                <th>Update Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td>#<?= $o['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($o['customer_name']) ?></strong></td>
                                    <td><?= format_rupiah($o['total_price']) ?></td>
                                    <td><?= htmlspecialchars($o['address'] ?? '') ?></td>
                                    <td>
                                        <span class="badge badge-primary" style="text-transform: uppercase;"><?= htmlspecialchars($o['status']) ?></span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:flex; gap:8px; align-items:center; margin:0; flex-wrap:wrap;">
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <select name="status" style="padding:8px 10px; border-radius:10px; background:rgba(15,23,42,0.9); color:white; border:1px solid rgba(148,163,184,0.2);">
                                                <option value="pending" <?= $o['status']=='pending'?'selected':'' ?>>Pending</option>
                                                <option value="paid" <?= $o['status']=='paid'?'selected':'' ?>>Paid</option>
                                                <option value="shipped" <?= $o['status']=='shipped'?'selected':'' ?>>Shipped</option>
                                                <option value="completed" <?= $o['status']=='completed'?'selected':'' ?>>Completed</option>
                                                <option value="cancelled" <?= $o['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">Simpan</button>
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