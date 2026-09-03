<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   AMBIL ORDER ID
========================================================= */
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id) {
    die("Order tidak valid.");
}

/* =========================================================
   CEK STRUKTUR TABEL ORDERS
   Supaya tidak lagi bergantung pada total_amount
========================================================= */
$columns = [];

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Tabel orders tidak dapat dibaca.");
}

$columnNames = array_column($columns, 'Field');

/* Cari primary key order */
$primaryKey = 'id';

foreach ($columns as $column) {
    if ($column['Key'] === 'PRI') {
        $primaryKey = $column['Field'];
        break;
    }
}

/* =========================================================
   AMBIL DATA ORDER
========================================================= */
$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE `$primaryKey` = ?
    LIMIT 1
");

$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Pesanan tidak ditemukan.");
}

/* =========================================================
   DETEKSI TOTAL PESANAN
========================================================= */

$totalColumns = [
    'total_amount',
    'grand_total',
    'total',
    'amount',
    'total_price'
];

$total = 0;

foreach ($totalColumns as $col) {
    if (isset($order[$col]) && is_numeric($order[$col])) {
        $total = (float) $order[$col];
        break;
    }
}

/* =========================================================
   DATA CUSTOMER
========================================================= */

$customerName =
    $order['customer_name']
    ?? $order['name']
    ?? $_SESSION['name']
    ?? 'Customer';

$customerEmail =
    $order['email']
    ?? $order['customer_email']
    ?? $_SESSION['email']
    ?? '-';

$status = $order['status'] ?? 'pending';

/* =========================================================
   PROSES PEMBAYARAN
========================================================= */

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (in_array('status', $columnNames, true)) {
        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE `$primaryKey` = ?
        ");

        $stmt->execute([
            'paid',
            $order_id
        ]);
    }

    if (in_array('payment_method', $columnNames, true)) {
        $stmt = $pdo->prepare("
            UPDATE orders
            SET payment_method = ?
            WHERE `$primaryKey` = ?
        ");

        $stmt->execute([
            'manual_payment',
            $order_id
        ]);
    }

    $success = true;
}

/* =========================================================
   FORMAT RUPIAH
========================================================= */

function rupiah($nominal)
{
    return 'Rp ' . number_format(
        (float)$nominal,
        0,
        ',',
        '.'
    );
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Pembayaran | Teddi Store</title>

<link rel="preconnect"
      href="https://fonts.googleapis.com">

<link rel="preconnect"
      href="https://fonts.gstatic.com"
      crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background:
        radial-gradient(
            circle at top left,
            rgba(99,102,241,.12),
            transparent 35%
        ),
        #f6f7fb;
    color: #172033;
    min-height: 100vh;
}

/* ================= HEADER ================= */

.header {
    height: 72px;
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(15px);
    border-bottom: 1px solid #e8eaf0;
    display: flex;
    align-items: center;
}

.header-inner {
    width: min(1120px, 92%);
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-size: 21px;
    font-weight: 800;
    color: #111827;
    text-decoration: none;
}

.logo span {
    color: #6366f1;
}

.back {
    text-decoration: none;
    color: #667085;
    font-size: 14px;
    font-weight: 600;
}

.back:hover {
    color: #6366f1;
}

/* ================= MAIN ================= */

.container {
    width: min(1120px, 92%);
    margin: auto;
}

.payment-wrapper {
    padding: 55px 0 80px;
}

.page-title {
    text-align: center;
    margin-bottom: 42px;
}

.page-title .badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #eef2ff;
    color: #4f46e5;
    padding: 8px 15px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 14px;
}

.page-title h1 {
    font-size: clamp(30px, 5vw, 44px);
    line-height: 1.1;
    letter-spacing: -1.5px;
}

.page-title p {
    color: #667085;
    margin-top: 12px;
}

/* ================= GRID ================= */

.payment-grid {
    display: grid;
    grid-template-columns: 1fr 390px;
    gap: 25px;
    align-items: start;
}

/* ================= CARD ================= */

.card {
    background: white;
    border: 1px solid #e8eaf0;
    border-radius: 22px;
    box-shadow: 0 15px 45px rgba(16,24,40,.07);
    overflow: hidden;
}

.card-header {
    padding: 25px 28px;
    border-bottom: 1px solid #edf0f5;
}

.card-header h2 {
    font-size: 19px;
}

.card-header p {
    margin-top: 5px;
    font-size: 13px;
    color: #667085;
}

.card-body {
    padding: 28px;
}

/* ================= ORDER ================= */

.order-number {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 17px;
    background: #f8f9fc;
    border-radius: 14px;
    margin-bottom: 22px;
}

.order-number span {
    font-size: 13px;
    color: #667085;
}

.order-number strong {
    font-size: 14px;
}

/* ================= CUSTOMER ================= */

.customer-box {
    display: grid;
    gap: 13px;
    margin-bottom: 25px;
}

.customer-item {
    display: flex;
    gap: 13px;
    align-items: center;
}

.customer-icon {
    width: 40px;
    height: 40px;
    background: #eef2ff;
    color: #6366f1;
    border-radius: 12px;
    display: grid;
    place-items: center;
}

.customer-item small {
    display: block;
    color: #98a2b3;
    font-size: 11px;
    margin-bottom: 2px;
}

.customer-item strong {
    font-size: 14px;
}

/* ================= PAYMENT METHOD ================= */

.method-title {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 13px;
}

.method-list {
    display: grid;
    gap: 12px;
}

.method {
    position: relative;
}

.method input {
    position: absolute;
    opacity: 0;
}

.method label {
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1.5px solid #e4e7ec;
    padding: 16px;
    border-radius: 15px;
    cursor: pointer;
    transition: .25s;
}

.method label:hover {
    border-color: #a5b4fc;
    transform: translateY(-1px);
}

.method input:checked + label {
    border-color: #6366f1;
    background: #f5f5ff;
    box-shadow: 0 0 0 3px rgba(99,102,241,.08);
}

.method-icon {
    width: 43px;
    height: 43px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: #f2f4f7;
    font-size: 20px;
}

.method-content {
    flex: 1;
}

.method-content strong {
    display: block;
    font-size: 14px;
}

.method-content span {
    display: block;
    margin-top: 3px;
    color: #98a2b3;
    font-size: 12px;
}

/* ================= SUMMARY ================= */

.summary {
    position: sticky;
    top: 25px;
}

.summary-header {
    padding: 25px;
    background:
        linear-gradient(
            135deg,
            #111827,
            #312e81
        );
    color: white;
}

.summary-header small {
    opacity: .7;
    font-size: 12px;
}

.summary-header h2 {
    margin-top: 7px;
    font-size: 27px;
}

.summary-body {
    padding: 25px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 0;
    color: #667085;
    font-size: 14px;
}

.summary-row strong {
    color: #172033;
}

.divider {
    height: 1px;
    background: #edf0f5;
    margin: 10px 0;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
}

.total-row span {
    font-size: 14px;
    color: #667085;
}

.total-row strong {
    font-size: 22px;
    color: #4f46e5;
}

/* ================= BUTTON ================= */

.pay-btn {
    width: 100%;
    border: none;
    margin-top: 23px;
    padding: 16px;
    border-radius: 14px;
    background: linear-gradient(
        135deg,
        #6366f1,
        #4f46e5
    );
    color: white;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 12px 25px rgba(79,70,229,.25);
    transition: .25s;
}

.pay-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 17px 30px rgba(79,70,229,.32);
}

.secure {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    color: #98a2b3;
    font-size: 11px;
    margin-top: 16px;
}

/* ================= ERROR ================= */

.error {
    background: #fef3f2;
    border: 1px solid #fecdca;
    color: #b42318;
    padding: 13px 15px;
    border-radius: 12px;
    font-size: 13px;
    margin-bottom: 20px;
}

/* ================= SUCCESS ================= */

.success-page {
    min-height: calc(100vh - 72px);
    display: grid;
    place-items: center;
    padding: 30px;
}

.success-card {
    width: min(520px, 100%);
    background: white;
    border: 1px solid #e8eaf0;
    border-radius: 26px;
    padding: 45px 35px;
    text-align: center;
    box-shadow: 0 25px 70px rgba(16,24,40,.1);
    animation: show .55s ease;
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 22px;
    border-radius: 50%;
    background: #ecfdf3;
    color: #12b76a;
    display: grid;
    place-items: center;
    font-size: 38px;
}

.success-card h1 {
    font-size: 30px;
    margin-bottom: 10px;
}

.success-card p {
    color: #667085;
    line-height: 1.7;
}

.success-order {
    background: #f8f9fc;
    border-radius: 14px;
    padding: 17px;
    margin: 25px 0;
}

.success-order span {
    display: block;
    color: #98a2b3;
    font-size: 12px;
}

.success-order strong {
    display: block;
    margin-top: 5px;
}

.home-btn {
    display: inline-flex;
    text-decoration: none;
    justify-content: center;
    width: 100%;
    padding: 15px;
    border-radius: 14px;
    background: #111827;
    color: white;
    font-weight: 700;
    font-size: 14px;
}

@keyframes show {
    from {
        opacity: 0;
        transform: translateY(20px) scale(.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* ================= RESPONSIVE ================= */

@media (max-width: 850px) {

    .payment-grid {
        grid-template-columns: 1fr;
    }

    .summary {
        position: static;
    }
}

@media (max-width: 500px) {

    .payment-wrapper {
        padding-top: 30px;
    }

    .card-body,
    .card-header,
    .summary-body {
        padding: 20px;
    }

    .page-title {
        margin-bottom: 25px;
    }

    .success-card {
        padding: 35px 22px;
    }
}

</style>
<link rel="stylesheet" href="../assets/style.css">

</head>

<body>

<header class="header">

    <div class="header-inner">

        <a href="../index.php" class="logo">
            Teddi<span>Store</span>
        </a>

        <a href="checkout.php" class="back">
            ← Kembali
        </a>

    </div>

</header>


<?php if ($success): ?>

<!-- =====================================================
     SUCCESS
===================================================== -->

<section class="success-page">

    <div class="receipt-card">

        <div class="receipt-header">
            <div class="success-icon">✓</div>

            <h1>Pembayaran Berhasil!</h1>

            <p>Terima kasih, pembayaran kamu telah berhasil diproses.</p>
        </div>

        <div class="receipt-line"></div>

        <div class="receipt-store">
            <h2>Teddi<span>Store</span></h2>
            <p>Struk Pembayaran</p>
        </div>

        <div class="receipt-line"></div>

        <div class="receipt-info">

            <div class="receipt-row">
                <span>Nomor Pesanan</span>
                <strong>#<?= htmlspecialchars($order_id) ?></strong>
            </div>

            <div class="receipt-row">
                <span>Nama Pemesan</span>
                <strong><?= htmlspecialchars($customerName) ?></strong>
            </div>

            <div class="receipt-row">
                <span>Email</span>
                <strong><?= htmlspecialchars($customerEmail) ?></strong>
            </div>

            <div class="receipt-row">
                <span>Metode Pembayaran</span>
                <strong>Manual Payment</strong>
            </div>

            <div class="receipt-row">
                <span>Status</span>
                <strong class="paid">LUNAS ✓</strong>
            </div>

        </div>

        <div class="receipt-line"></div>

        <div class="receipt-total">
            <span>Total Pembayaran</span>
            <strong><?= rupiah($total) ?></strong>
        </div>

        <div class="receipt-actions">

            <button onclick="window.print()" class="print-btn">
                🖨️ Cetak Struk
            </button>

            <a href="history.php" class="home-btn">
                Lihat Riwayat Pesanan
            </a>

        </div>

    </div>

</section>


<?php else: ?>

<!-- =====================================================
     PAYMENT
===================================================== -->

<main class="payment-wrapper">

    <div class="container">

        <div class="page-title">

            <div class="badge">
                🔒 Pembayaran Aman
            </div>

            <h1>Selesaikan Pembayaran</h1>

            <p>
                Periksa pesanan kamu sebelum melakukan pembayaran.
            </p>

        </div>


        <div class="payment-grid">


            <!-- ================= LEFT ================= -->

            <div class="card">

                <div class="card-header">

                    <h2>Detail Pembayaran</h2>

                    <p>
                        Pilih metode pembayaran yang kamu inginkan.
                    </p>

                </div>


                <div class="card-body">

                    <div class="order-number">

                        <span>
                            Nomor Pesanan
                        </span>

                        <strong>
                            #<?= htmlspecialchars($order_id) ?>
                        </strong>

                    </div>


                    <div class="customer-box">

                        <div class="customer-item">

                            <div class="customer-icon">
                                👤
                            </div>

                            <div>

                                <small>
                                    Nama Pemesan
                                </small>

                                <strong>
                                    <?= htmlspecialchars($customerName) ?>
                                </strong>

                            </div>

                        </div>


                        <div class="customer-item">

                            <div class="customer-icon">
                                ✉️
                            </div>

                            <div>

                                <small>
                                    Email
                                </small>

                                <strong>
                                    <?= htmlspecialchars($customerEmail) ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <form method="POST">
                        <button
                            type="submit"
                            class="pay-btn"
                        >
                            Bayar Sekarang →
                        </button>

                        <div class="secure">
                            🔒 Transaksi kamu aman dan terlindungi
                        </div>
                    </form>

                </div>

            </div>


            <!-- ================= RIGHT ================= -->

            <aside class="card summary">

                <div class="summary-header">

                    <small>
                        TOTAL PEMBAYARAN
                    </small>

                    <h2>
                        <?= rupiah($total) ?>
                    </h2>

                </div>


                <div class="summary-body">

                    <div class="summary-row">

                        <span>
                            Nomor Pesanan
                        </span>

                        <strong>
                            #<?= htmlspecialchars($order_id) ?>
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Status
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                ucfirst($status)
                            ) ?>
                        </strong>

                    </div>


                    <div class="divider"></div>


                    <div class="total-row">

                        <span>
                            Total
                        </span>

                        <strong>
                            <?= rupiah($total) ?>
                        </strong>

                    </div>

                </div>

            </aside>

        </div>

    </div>

</main>

<?php endif; ?>


</body>
</html>