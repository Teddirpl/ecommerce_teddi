<?php

require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| USER LOGIN
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../login.php");
    exit;
}

$primaryKey = 'id';
$userColumn = 'user_id';

try {
    $columnQuery = $pdo->query("SHOW COLUMNS FROM orders");
    $columns = $columnQuery->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $column) {
        if ($column === 'order_id') {
            $primaryKey = 'order_id';
            break;
        }
    }

    foreach (['user_id', 'customer_id'] as $col) {
        if (in_array($col, $columns, true)) {
            $userColumn = $col;
            break;
        }
    }
} catch (PDOException $e) {
    $columns = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_history'])) {
    $delete_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    if ($delete_id > 0) {
        if ($userColumn && in_array($userColumn, ['user_id', 'customer_id'], true)) {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE `$primaryKey` = ? AND `$userColumn` = ?");
            $stmt->execute([$delete_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE `$primaryKey` = ?");
            $stmt->execute([$delete_id]);
        }
        header('Location: history.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function rupiah($nominal)
{
    return 'Rp ' . number_format(
        (float) $nominal,
        0,
        ',',
        '.'
    );
}

function getOrderTotal($order)
{
    /*
    | Cari nama kolom total yang tersedia.
    */

    $possibleColumns = [
        'grand_total',
        'total',
        'amount',
        'total_price',
        'subtotal'
    ];

    foreach ($possibleColumns as $column) {

        if (
            isset($order[$column]) &&
            is_numeric($order[$column])
        ) {
            return (float) $order[$column];
        }
    }

    return 0;
}

/*
|--------------------------------------------------------------------------
| AMBIL PESANAN
|--------------------------------------------------------------------------
|
| Kita gunakan SELECT * supaya tidak meminta
| kolom total_amount yang tidak ada.
|
*/

try {

    /*
     * Cek struktur tabel orders
     */
    $columnQuery = $pdo->query("SHOW COLUMNS FROM orders");

    $columns = $columnQuery->fetchAll(PDO::FETCH_COLUMN);

    /*
     * Cari primary key tabel orders
     */
    $primaryKey = 'id';

    foreach ($columns as $column) {

        if ($column === 'order_id') {
            $primaryKey = 'order_id';
            break;
        }
    }

    /*
     * Cari kolom user/customer
     */
    $userColumn = null;

    $possibleUserColumns = [
        'user_id',
        'customer_id'
    ];

    foreach ($possibleUserColumns as $column) {

        if (in_array($column, $columns)) {
            $userColumn = $column;
            break;
        }
    }

    /*
     * Jika ada user_id/customer_id
     */
    if ($userColumn) {

        $sql = "
            SELECT *
            FROM orders
            WHERE `$userColumn` = ?
            ORDER BY `$primaryKey` DESC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $user_id
        ]);

    } else {

        /*
         * Fallback apabila tabel orders
         * tidak memiliki user_id.
         */

        $sql = "
            SELECT *
            FROM orders
            ORDER BY `$primaryKey` DESC
        ";

        $stmt = $pdo->query($sql);
    }

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            color:#b42318;
        '>
            <h2>Database Error</h2>
            <p>
                " . htmlspecialchars($e->getMessage()) . "
            </p>
        </div>
    ");
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Riwayat Pembelian | Teddi Store</title>

<link rel="preconnect"
      href="https://fonts.googleapis.com">

<link rel="preconnect"
      href="https://fonts.gstatic.com"
      crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet">


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* =========================================================
   BODY
========================================================= */

body {

    font-family: 'Inter', sans-serif;

    min-height: 100vh;

    color: #172033;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(99,102,241,.20),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 20%,
            rgba(236,72,153,.14),
            transparent 30%
        ),

        #f6f7fb;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 72px;

    background: rgba(255,255,255,.88);

    backdrop-filter: blur(18px);

    border-bottom: 1px solid #e5e7eb;

    position: sticky;

    top: 0;

    z-index: 100;
}


.header-inner {

    width: min(1120px, 92%);

    height: 100%;

    margin: auto;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.logo {

    text-decoration: none;

    font-size: 21px;

    font-weight: 800;

    color: #111827;
}


.logo span {

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #ec4899
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;
}


.nav {

    display: flex;

    align-items: center;

    gap: 10px;
}


.nav a {

    text-decoration: none;

    color: #344054;

    font-size: 13px;

    font-weight: 600;

    padding: 10px 14px;

    border-radius: 12px;

    transition: .25s;
}


.nav a:hover {

    background: #f2f4f7;

    color: #6366f1;
}


.nav .active {

    background: #eef2ff;

    color: #4f46e5;
}


.logout {

    color: #ef4444 !important;
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    width: min(1120px, 92%);

    margin: auto;
}


/* =========================================================
   MAIN
========================================================= */

main {

    padding: 70px 0 100px;
}


/* =========================================================
   HERO
========================================================= */

.hero {

    position: relative;

    overflow: hidden;

    padding: 35px 40px;

    border-radius: 26px;

    color: white;

    background:

        linear-gradient(
            135deg,
            #172554,
            #1d4ed8 60%,
            #4f46e5
        );

    box-shadow:
        0 25px 60px rgba(37,99,235,.25);

    margin-bottom: 25px;
}


.hero::before {

    content: '';

    position: absolute;

    width: 230px;

    height: 230px;

    border-radius: 50%;

    background: rgba(255,255,255,.08);

    right: -80px;

    top: -100px;
}


.hero-content {

    position: relative;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 25px;
}


.hero-left small {

    display: inline-block;

    padding: 7px 12px;

    border: 1px solid rgba(255,255,255,.2);

    background: rgba(255,255,255,.08);

    border-radius: 999px;

    font-size: 10px;

    letter-spacing: 1px;

    font-weight: 800;

    margin-bottom: 12px;
}


.hero-left h1 {

    font-size: clamp(30px, 5vw, 43px);

    letter-spacing: -1.5px;

    line-height: 1.1;
}


.hero-left p {

    margin-top: 10px;

    color: rgba(255,255,255,.72);

    font-size: 13px;
}


.shop-btn {

    text-decoration: none;

    color: white;

    background:
        linear-gradient(
            135deg,
            #ec4899,
            #f43f5e
        );

    padding: 14px 20px;

    border-radius: 13px;

    font-size: 12px;

    font-weight: 800;

    white-space: nowrap;

    box-shadow:
        0 12px 25px rgba(236,72,153,.25);

    transition: .25s;
}


.shop-btn:hover {

    transform: translateY(-3px);

    box-shadow:
        0 17px 30px rgba(236,72,153,.35);
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 24px;

    padding: 70px 30px;

    text-align: center;

    box-shadow:
        0 15px 40px rgba(16,24,40,.06);
}


.empty-icon {

    width: 75px;

    height: 75px;

    border-radius: 22px;

    background: #eef2ff;

    display: grid;

    place-items: center;

    font-size: 35px;

    margin: 0 auto 20px;
}


.empty h2 {

    font-size: 22px;

    margin-bottom: 8px;
}


.empty p {

    color: #667085;

    font-size: 13px;

    margin-bottom: 25px;
}


/* =========================================================
   ORDERS GRID
========================================================= */

.orders-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;
}


/* =========================================================
   ORDER CARD
========================================================= */

.order-card {

    background: rgba(255,255,255,.92);

    border: 1px solid #e4e7ec;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 15px 35px rgba(16,24,40,.06);

    transition:
        transform .25s,
        box-shadow .25s,
        border-color .25s;

    animation: cardShow .5s ease both;
}


.order-card:hover {

    transform: translateY(-5px);

    border-color: #c7d2fe;

    box-shadow:
        0 22px 45px rgba(16,24,40,.10);
}


@keyframes cardShow {

    from {

        opacity: 0;

        transform: translateY(15px);
    }

    to {

        opacity: 1;

        transform: translateY(0);
    }
}


/* =========================================================
   CARD HEADER
========================================================= */

.order-header {

    padding: 20px;

    border-bottom: 1px solid #edf0f5;

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 10px;
}


.order-title {

    font-size: 14px;

    font-weight: 800;
}


.order-date {

    color: #98a2b3;

    font-size: 11px;

    margin-top: 6px;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 7px 10px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}


.status.paid {

    color: #027a48;

    background: #ecfdf3;

    border: 1px solid #abefc6;
}


.status.pending {

    color: #b54708;

    background: #fffaeb;

    border: 1px solid #fedf89;
}


.status.cancelled {

    color: #b42318;

    background: #fef3f2;

    border: 1px solid #fecdca;
}


/* =========================================================
   CARD BODY
========================================================= */

.order-body {

    padding: 20px;
}


.product {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 18px;
}


.product-icon {

    width: 45px;

    height: 45px;

    flex-shrink: 0;

    border-radius: 13px;

    display: grid;

    place-items: center;

    background:
        linear-gradient(
            135deg,
            #eef2ff,
            #fdf2f8
        );

    font-size: 20px;
}


.product-name {

    font-size: 13px;

    font-weight: 700;

    line-height: 1.5;
}


.product-label {

    display: block;

    color: #98a2b3;

    font-size: 10px;

    margin-bottom: 2px;
}


/* =========================================================
   TOTAL
========================================================= */

.total-box {

    border-top: 1px dashed #d0d5dd;

    padding-top: 15px;

    display: flex;

    align-items: flex-end;

    justify-content: space-between;
}


.total-label {

    color: #667085;

    font-size: 11px;
}


.total {

    color: #4f46e5;

    font-size: 18px;

    font-weight: 800;

    margin-top: 4px;
}


/* =========================================================
   DETAIL BUTTON
========================================================= */

.detail-btn {

    display: flex;

    justify-content: center;

    align-items: center;

    text-decoration: none;

    width: 100%;

    margin-top: 16px;

    padding: 11px;

    border-radius: 11px;

    background: #f8f9fc;

    color: #344054;

    font-size: 11px;

    font-weight: 700;

    transition: .2s;
}


.detail-btn:hover {

    background: #eef2ff;

    color: #4f46e5;
}

.order-actions {
    display: grid;
    gap: 10px;
    margin-top: 16px;
}

.delete-btn {
    width: 100%;
    border: none;
    border-radius: 11px;
    padding: 11px;
    background: #fef3f2;
    color: #b42318;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.delete-btn:hover {
    background: #fee4e2;
    color: #9f1239;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    margin-top: 35px;

    color: #98a2b3;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .orders-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media (max-width: 650px) {

    .header {

        height: auto;

        padding: 15px 0;
    }

    .header-inner {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }

    .nav {

        width: 100%;

        overflow-x: auto;
    }

    .hero {

        padding: 28px 24px;
    }

    .hero-content {

        align-items: flex-start;

        flex-direction: column;
    }

    .shop-btn {

        width: 100%;

        text-align: center;
    }

    .orders-grid {

        grid-template-columns: 1fr;
    }

}

</style>
<link rel="stylesheet" href="../assets/style.css">

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="header">

    <div class="header-inner">

        <a href="../index.php"
           class="logo">

            Teddi <span>Store</span>

        </a>


        <nav class="nav">

            <a href="../index.php">
                Home
            </a>

            <a href="shop.php">
                Keranjang
            </a>

            <a href="history.php"
               class="active">
                Riwayat
            </a>

            <a href="../logout.php"
               class="logout">
                Logout
            </a>

        </nav>

    </div>

</header>



<!-- =====================================================
     MAIN
===================================================== -->

<main>

    <div class="container">


        <!-- HERO -->

        <section class="hero">

            <div class="hero-content">

                <div class="hero-left">

                    <small>
                        TEDDI STORE
                    </small>

                    <h1>
                        📋 Riwayat Pembelian
                    </h1>

                    <p>
                        Lihat semua pesanan dan transaksi
                        yang pernah kamu lakukan.
                    </p>

                </div>


                <a href="shop.php"
                   class="shop-btn">

                    ← Belanja Lagi

                </a>

            </div>

        </section>



        <?php if (empty($orders)): ?>


            <!-- EMPTY -->

            <section class="empty">

                <div class="empty-icon">
                    🛍️
                </div>

                <h2>
                    Belum Ada Pesanan
                </h2>

                <p>
                    Kamu belum memiliki riwayat pembelian.
                </p>

                <a href="shop.php"
                   class="shop-btn">

                    Mulai Belanja

                </a>

            </section>


        <?php else: ?>


            <!-- ORDERS -->

            <section class="orders-grid">


                <?php foreach ($orders as $index => $order): ?>


                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | ORDER ID
                    |--------------------------------------------------------------------------
                    */

                    $orderId =
                        $order['id']
                        ?? $order['order_id']
                        ?? '-';


                    /*
                    |--------------------------------------------------------------------------
                    | PRODUCT NAME
                    |--------------------------------------------------------------------------
                    */

                    $productName =
                        $order['product_name']
                        ?? $order['name']
                        ?? $order['product']
                        ?? 'Pesanan Produk';


                    /*
                    |--------------------------------------------------------------------------
                    | TOTAL
                    |--------------------------------------------------------------------------
                    */

                    $total =
                        getOrderTotal($order);


                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */

                    $status =
                        strtolower(
                            $order['status']
                            ?? 'pending'
                        );


                    if (
                        $status === 'paid' ||
                        $status === 'dibayar' ||
                        $status === 'success' ||
                        $status === 'completed'
                    ) {

                        $statusClass = 'paid';

                        $statusText = '✓ Dibayar';

                    } elseif (
                        $status === 'cancelled' ||
                        $status === 'canceled' ||
                        $status === 'batal'
                    ) {

                        $statusClass = 'cancelled';

                        $statusText = '✕ Dibatalkan';

                    } else {

                        $statusClass = 'pending';

                        $statusText = '⌛ Menunggu';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | DATE
                    |--------------------------------------------------------------------------
                    */

                    $date =
                        $order['created_at']
                        ?? $order['order_date']
                        ?? $order['created']
                        ?? null;


                    if ($date) {

                        $formattedDate =
                            date(
                                'd M Y, H:i',
                                strtotime($date)
                            );

                    } else {

                        $formattedDate = '-';

                    }

                    ?>


                    <article
                        class="order-card"
                        style="
                            animation-delay:
                            <?= $index * 0.05 ?>s
                        "
                    >


                        <!-- CARD HEADER -->

                        <div class="order-header">

                            <div>

                                <div class="order-title">

                                    Pesanan #<?= htmlspecialchars($orderId) ?>

                                </div>

                                <div class="order-date">

                                    <?= htmlspecialchars($formattedDate) ?>

                                </div>

                            </div>


                            <div
                                class="
                                    status
                                    <?= $statusClass ?>
                                "
                            >

                                <?= $statusText ?>

                            </div>

                        </div>



                        <!-- CARD BODY -->

                        <div class="order-body">


                            <div class="product">

                                <div class="product-icon">
                                    🛍️
                                </div>

                                <div>

                                    <span class="product-label">
                                        Produk
                                    </span>

                                    <div class="product-name">

                                        <?= htmlspecialchars(
                                            $productName
                                        ) ?>

                                    </div>

                                </div>

                            </div>



                            <div class="total-box">

                                <div>

                                    <div class="total-label">
                                        Total Pembayaran
                                    </div>

                                    <div class="total">

                                        <?= rupiah($total) ?>

                                    </div>

                                </div>

                            </div>


                            <div class="order-actions">
                                <?php if ($orderId !== '-'): ?>
                                    <a
                                        href="payment.php?order_id=<?= urlencode($orderId) ?>"
                                        class="detail-btn"
                                    >
                                        Lihat Detail →
                                    </a>
                                <?php endif; ?>

                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Hapus riwayat pembelian ini?')">
                                    <input type="hidden" name="delete_history" value="1">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars((string) $orderId) ?>">
                                    <button type="submit" class="delete-btn">
                                        Hapus Riwayat
                                    </button>
                                </form>
                            </div>

                        </div>

                    </article>


                <?php endforeach; ?>


            </section>


        <?php endif; ?>


        <div class="footer">

            © <?= date('Y') ?> Teddi Store.
            Semua transaksi tersimpan dengan aman.

        </div>


    </div>

</main>


</body>

</html>