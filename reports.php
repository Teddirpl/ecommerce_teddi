<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_admin();

 $sales = $pdo->query("
    SELECT o.id, u.name as customer, o.total_price as total_amount, o.created_at 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.status = 'completed'
    ORDER BY o.created_at DESC
")->fetchAll();

// Ringkasan
 $totalTransaksi  = count($sales);
 $totalPendapatan = array_sum(array_column($sales, 'total_amount'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Teddi Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/script.js" defer></script>
    <style>
        .btn-print {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s;
        }
        .btn-print:hover { opacity: .85; }

        /* Kartu ringkasan (hanya tampil di layar) */
        .report-summary {
            display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .summary-card {
            flex: 1; min-width: 200px;
            background: rgba(30, 41, 59, .5);
            border: 1px solid rgba(148,163,184,.15);
            border-radius: 12px;
            padding: 16px 20px;
        }
        .summary-card .label { font-size: .8rem; color: #94a3b8; margin-bottom: 6px; }
        .summary-card .value { font-size: 1.4rem; font-weight: 700; color: #34d399; }
        .summary-card .value.blue { color: #60a5fa; }

        /* Header khusus cetak — disembunyikan di layar */
        .print-header { display: none; }
        .print-footer { display: none; }

        /* ================= PRINT MODE ================= */
        @media print {
            @page { margin: 15mm; size: A4; }

            /* Sembunyikan yang tidak perlu dicetak */
            .admin-sidebar,
            .admin-topbar,
            .no-print,
            .report-summary {
                display: none !important;
            }

            /* Paksa tema terang */
            body { background: #fff !important; color: #000 !important; }
            .admin-layout { display: block; }
            .admin-main { padding: 0 !important; }

            /* Tampilkan kop laporan */
            .print-header {
                display: block;
                text-align: center;
                border-bottom: 3px double #000;
                padding-bottom: 12px;
                margin-bottom: 20px;
            }
            .print-header h2 { margin: 0 0 4px; font-size: 18pt; color: #000; }
            .print-header p  { margin: 2px 0; font-size: 10pt; color: #333; }

            .admin-panel {
                background: #fff !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .admin-panel h2 { color: #000 !important; }

            .admin-table-wrap { overflow: visible !important; border: none !important; }

            .admin-table { width: 100%; border-collapse: collapse; }
            .admin-table th {
                background: #e5e7eb !important;
                color: #000 !important;
                border: 1px solid #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .admin-table td {
                border: 1px solid #000;
                color: #000 !important;
                background: #fff !important;
            }
            tfoot td {
                font-weight: 700;
                background: #f3f4f6 !important;
                -webkit-print-color-adjust: exact;
            }

            /* Tanda tangan di bawah laporan */
            .print-footer {
                display: block;
                margin-top: 40px;
                text-align: right;
                font-size: 11pt;
                color: #000;
            }
            .print-footer .space { height: 60px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="brand">Teddi Admin</div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="categories.php">Kategori</a>
                <a href="products.php">Produk</a>
                <a href="orders.php">Pesanan</a>
                <a href="reports.php" class="active">Laporan</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <h1>Laporan Penjualan</h1>
                <div style="display:flex; align-items:center; gap:14px;">
                    <button onclick="window.print()" class="btn-print no-print">🖨️ Cetak Laporan</button>
                    <div class="admin-user">Halo, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
                </div>
            </header>

            <!-- Kop laporan: hanya muncul saat dicetak -->
            <div class="print-header">
                <h2>TEDDI STORE</h2>
                <p><strong>Laporan Penjualan (Transaksi Selesai)</strong></p>
                <p>Dicetak: <?= date('d/m/Y H:i') ?> WIB oleh <?= htmlspecialchars($_SESSION['user_name']) ?></p>
            </div>

            <!-- Ringkasan: hanya tampil di layar -->
            <div class="report-summary no-print">
                <div class="summary-card">
                    <div class="label">Total Transaksi Sukses</div>
                    <div class="value blue"><?= $totalTransaksi ?></div>
                </div>
                <div class="summary-card">
                    <div class="label">Total Pendapatan</div>
                    <div class="value"><?= format_rupiah($totalPendapatan) ?></div>
                </div>
            </div>

            <section class="admin-panel">
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID Transaksi</th>
                                <th>Nama Pelanggan</th>
                                <th>Total Pembayaran</th>
                                <th>Tanggal Pembelian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($sales)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:22px;">Belum ada transaksi sukses.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sales as $s): ?>
                                    <tr>
                                        <td>#<?= $s['id'] ?></td>
                                        <td><?= htmlspecialchars($s['customer']) ?></td>
                                        <td class="amount"><?= format_rupiah($s['total_amount']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">TOTAL (<?= $totalTransaksi ?> transaksi)</td>
                                <td><?= format_rupiah($totalPendapatan) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Blok tanda tangan: hanya muncul saat dicetak -->
                <div class="print-footer">
                    <p>Mengetahui,</p>
                    <div class="space"></div>
                    <p>( <?= htmlspecialchars($_SESSION['user_name']) ?> )</p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>