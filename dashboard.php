<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_admin();

$total_orders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0;
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-Commerce Teddi</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/script.js" defer></script>
    <style>
        :root {
            --bg: #f4f7fb;
            --bg-soft: #eef4ff;
            --panel: #ffffff;
            --panel-alt: #f8fafc;
            --line: #e2e8f0;
            --text: #0f172a;
            --muted: #475569;
            --primary: #4f46e5;
            --primary-2: #7c3aed;
            --primary-soft: #eef2ff;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg-soft), var(--bg));
            color: var(--text);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .admin-shell {
            min-height: 100vh;
            display: flex;
            background: var(--bg);
        }

        .sidebar {
            width: 260px;
            background: var(--panel);
            border-right: 1px solid var(--line);
            padding: 28px 20px;
            box-shadow: 0 8px 22px var(--shadow);
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 28px;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav a {
            display: block;
            padding: 12px 14px;
            border-radius: 12px;
            color: var(--muted);
            border: 1px solid transparent;
            transition: 0.25s ease;
            font-weight: 600;
        }

        .nav a:hover,
        .nav a.active {
            background: linear-gradient(135deg, var(--primary-soft), rgba(79, 70, 229, 0.06));
            border-color: rgba(79, 70, 229, 0.18);
            color: var(--primary);
            transform: translateX(3px);
        }

        .content {
            flex: 1;
            padding: 26px;
        }

        .topbar {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 22px var(--shadow);
        }

        .topbar h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--text);
        }

        .user-pill {
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid rgba(79, 70, 229, 0.15);
            border-radius: 999px;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .stats-grid {
            margin-top: 26px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px 20px;
            box-shadow: 0 12px 24px var(--shadow);
        }

        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), transparent);
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, var(--warning), transparent);
        }

        .stat-card.success::before {
            background: linear-gradient(90deg, var(--success), transparent);
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .stat-value {
            margin: 0;
            font-size: clamp(1.8rem, 2vw, 2.4rem);
            font-weight: 800;
            color: var(--text);
        }

        .quick-grid {
            margin-top: 28px;
            display: grid;
            grid-template-columns: 1.4fr 0.8fr;
            gap: 20px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 24px var(--shadow);
        }

        .panel h3 {
            margin-top: 0;
            margin-bottom: 18px;
            color: var(--text);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 14px;
        }

        .action {
            display: block;
            background: linear-gradient(135deg, var(--primary-soft), #ffffff);
            border: 1px solid rgba(79, 70, 229, 0.12);
            border-radius: 14px;
            padding: 16px 12px;
            text-align: center;
            font-weight: 700;
            color: var(--primary);
            transition: 0.25s ease;
        }

        .action:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 18px rgba(79, 70, 229, 0.1);
        }

        .mini-box {
            background: var(--panel-alt);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }

        .mini-box:last-child {
            margin-bottom: 0;
        }

        .mini-label {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .mini-value {
            margin-top: 6px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }

        @media (max-width: 900px) {
            .admin-shell {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--line);
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand">Teddi Admin</div>
            <nav class="nav">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="categories.php">Kategori</a>
                <a href="products.php">Produk</a>
                <a href="orders.php">Pesanan</a>
                <a href="reports.php">Laporan</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </aside>

        <main class="content">
            <header class="topbar">
                <h1>Panel Dashboard Admin</h1>
                <div class="user-pill">Halo, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
            </header>

            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Produk</div>
                    <h2 class="stat-value"><?= $total_products ?></h2>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Total Transaksi</div>
                    <h2 class="stat-value"><?= $total_orders ?></h2>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Total Pendapatan Sukses</div>
                    <h2 class="stat-value">Rp <?= number_format($total_revenue, 0, ',', '.') ?></h2>
                </div>
            </section>

            <section class="quick-grid">
                <div class="panel">
                    <h3>Menu Cepat</h3>
                    <div class="quick-actions">
                        <a href="products.php" class="action">Tambah Produk</a>
                        <a href="categories.php" class="action">Kategori</a>
                        <a href="orders.php" class="action">Pesanan</a>
                        <a href="reports.php" class="action">Laporan</a>
                    </div>
                </div>

                <div class="panel">
                    <h3>Ringkasan</h3>
                    <div class="mini-box">
                        <div class="mini-label">Status Sistem</div>
                        <div class="mini-value" style="color: var(--success);">Online</div>
                    </div>
                    <div class="mini-box">
                        <div class="mini-label">Akses</div>
                        <div class="mini-value">Admin</div>
                    </div>
                    <div class="mini-box">
                        <div class="mini-label">User Aktif</div>
                        <div class="mini-value"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>