<?php

require_once '../config/database.php';
require_once '../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* =========================================================
   FUNGSI RUPIAH
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

/* =========================================================
   AMBIL CART
========================================================= */

/*
|--------------------------------------------------------------------------
| Sesuaikan dengan sistem cart kamu.
| Sistem mencoba mengambil cart dari session.
|--------------------------------------------------------------------------
*/

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    $stmt = $pdo->prepare("SELECT c.product_id, c.quantity, p.name AS product_name, p.price, p.image FROM carts c JOIN products p ON p.id = c.product_id WHERE c.user_id = ? ORDER BY c.id DESC");
    $stmt->execute([$user_id]);
    $dbCart = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbCart as $item) {
        $cart[] = [
            'product_id' => (int) $item['product_id'],
            'product_name' => $item['product_name'],
            'price' => (float) $item['price'],
            'quantity' => (int) $item['quantity'],
            'image' => $item['image'],
        ];
    }

    if (!empty($cart)) {
        $_SESSION['cart'] = $cart;
    }
}

if (empty($cart)) {
    header("Location: cart.php");
    exit;
}


/* =========================================================
   NORMALISASI CART
========================================================= */

$items = [];

$total = 0;

foreach ($cart as $key => $item) {

    /*
    |-----------------------------------------
    | ID PRODUK
    |-----------------------------------------
    */

    $product_id =
        $item['product_id']
        ?? $item['id']
        ?? $key;


    /*
    |-----------------------------------------
    | NAMA PRODUK
    |-----------------------------------------
    */

    $product_name =
        $item['product_name']
        ?? $item['name']
        ?? 'Produk';


    /*
    |-----------------------------------------
    | HARGA
    |-----------------------------------------
    */

    $price =
        $item['price']
        ?? $item['harga']
        ?? 0;


    /*
    |-----------------------------------------
    | JUMLAH
    |-----------------------------------------
    */

    $quantity =
        $item['quantity']
        ?? $item['qty']
        ?? $item['jumlah']
        ?? 1;


    /*
    |-----------------------------------------
    | GAMBAR
    |-----------------------------------------
    */

    $image =
        $item['image']
        ?? $item['gambar']
        ?? '';


    $subtotal = $price * $quantity;

    $total += $subtotal;


    $items[] = [
        'product_id' => $product_id,
        'product_name' => $product_name,
        'price' => $price,
        'quantity' => $quantity,
        'image' => $image,
        'subtotal' => $subtotal
    ];
}


/* =========================================================
   PROSES CHECKOUT
========================================================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $purchase_type =
        $_POST['purchase_type']
        ?? 'offline';

    $payment_method =
        $_POST['payment_method']
        ?? 'bank_transfer';


    /*
    |--------------------------------------------------------------------------
    | VALIDASI PEMBELIAN
    |--------------------------------------------------------------------------
    */

    $allowedPurchase = [
        'offline',
        'online'
    ];

    if (!in_array($purchase_type, $allowedPurchase, true)) {

        $error =
            'Jenis pembelian tidak valid.';

    }


    /*
    |--------------------------------------------------------------------------
    | VALIDASI PAYMENT
    |--------------------------------------------------------------------------
    */

    $allowedPayment = [
        'bank_transfer',
        'ewallet',
        'cod',
        'installment'
    ];

    if (
        !$error &&
        !in_array(
            $payment_method,
            $allowedPayment,
            true
        )
    ) {

        $error =
            'Metode pembayaran tidak valid.';
    }


    /*
    |--------------------------------------------------------------------------
    | BUAT ORDER
    |--------------------------------------------------------------------------
    */

    if (!$error) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CEK KOLOM ORDERS
            |--------------------------------------------------------------------------
            */

            $columnQuery =
                $pdo->query(
                    "SHOW COLUMNS FROM orders"
                );

            $columns =
                $columnQuery->fetchAll(
                    PDO::FETCH_COLUMN
                );


            /*
            |--------------------------------------------------------------------------
            | TENTUKAN PRIMARY KEY
            |--------------------------------------------------------------------------
            */

            $primaryKey = 'id';

            if (
                in_array(
                    'order_id',
                    $columns,
                    true
                )
            ) {

                $primaryKey = 'order_id';
            }


            /*
            |--------------------------------------------------------------------------
            | SIAPKAN DATA ORDER
            |--------------------------------------------------------------------------
            */

            $data = [];


            /*
            |--------------------------------------------------------------------------
            | USER ID
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'user_id',
                    $columns,
                    true
                )
            ) {

                $data['user_id'] =
                    $user_id;
            }

            elseif (
                in_array(
                    'customer_id',
                    $columns,
                    true
                )
            ) {

                $data['customer_id'] =
                    $user_id;
            }


            /*
            |--------------------------------------------------------------------------
            | TOTAL
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'total_amount',
                    $columns,
                    true
                )
            ) {

                $data['total_amount'] =
                    $total;
            }

            elseif (
                in_array(
                    'grand_total',
                    $columns,
                    true
                )
            ) {

                $data['grand_total'] =
                    $total;
            }

            elseif (
                in_array(
                    'total',
                    $columns,
                    true
                )
            ) {

                $data['total'] =
                    $total;
            }

            elseif (
                in_array(
                    'total_price',
                    $columns,
                    true
                )
            ) {

                $data['total_price'] =
                    $total;
            }


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'status',
                    $columns,
                    true
                )
            ) {

                $data['status'] =
                    'pending';
            }


            /*
            |--------------------------------------------------------------------------
            | PAYMENT METHOD
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'payment_method',
                    $columns,
                    true
                )
            ) {

                $data['payment_method'] =
                    $payment_method;
            }


            /*
            |--------------------------------------------------------------------------
            | PURCHASE TYPE
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'purchase_type',
                    $columns,
                    true
                )
            ) {

                $data['purchase_type'] =
                    $purchase_type;
            }

            elseif (
                in_array(
                    'order_type',
                    $columns,
                    true
                )
            ) {

                $data['order_type'] =
                    $purchase_type;
            }


            /*
            |--------------------------------------------------------------------------
            | CREATED AT
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'created_at',
                    $columns,
                    true
                )
            ) {

                $data['created_at'] =
                    date('Y-m-d H:i:s');
            }


            /*
            |--------------------------------------------------------------------------
            | JIKA TIDAK ADA KOLOM YANG COCOK
            |--------------------------------------------------------------------------
            */

            if (empty($data)) {

                throw new Exception(
                    'Struktur tabel orders tidak sesuai.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT ORDER
            |--------------------------------------------------------------------------
            */

            $fields =
                array_keys($data);

            $placeholders =
                array_fill(
                    0,
                    count($fields),
                    '?'
                );

            $sql = "
                INSERT INTO orders
                (`" .
                implode(
                    "`, `",
                    $fields
                ) .
                "`)
                VALUES (" .
                implode(
                    ', ',
                    $placeholders
                ) .
                ")
            ";


            $stmt =
                $pdo->prepare($sql);


            $stmt->execute(
                array_values($data)
            );


            /*
            |--------------------------------------------------------------------------
            | AMBIL ORDER ID
            |--------------------------------------------------------------------------
            */

            $order_id =
                $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | JIKA TABEL ORDER_ITEMS ADA
            |--------------------------------------------------------------------------
            */

            try {

                $check =
                    $pdo->query(
                        "SHOW TABLES LIKE 'order_items'"
                    );

                $orderItemsExists =
                    $check->rowCount() > 0;


                if ($orderItemsExists) {

                    $itemColumnsQuery =
                        $pdo->query(
                            "SHOW COLUMNS FROM order_items"
                        );

                    $itemColumns =
                        $itemColumnsQuery->fetchAll(
                            PDO::FETCH_COLUMN
                        );


                    foreach ($items as $item) {

                        $itemData = [];


                        /*
                        |-----------------------------------------
                        | ORDER ID
                        |-----------------------------------------
                        */

                        if (
                            in_array(
                                'order_id',
                                $itemColumns,
                                true
                            )
                        ) {

                            $itemData['order_id'] =
                                $order_id;
                        }


                        /*
                        |-----------------------------------------
                        | PRODUCT ID
                        |-----------------------------------------
                        */

                        if (
                            in_array(
                                'product_id',
                                $itemColumns,
                                true
                            )
                        ) {

                            $itemData['product_id'] =
                                $item['product_id'];
                        }


                        /*
                        |-----------------------------------------
                        | QUANTITY
                        |-----------------------------------------
                        */

                        if (
                            in_array(
                                'quantity',
                                $itemColumns,
                                true
                            )
                        ) {

                            $itemData['quantity'] =
                                $item['quantity'];
                        }

                        elseif (
                            in_array(
                                'qty',
                                $itemColumns,
                                true
                            )
                        ) {

                            $itemData['qty'] =
                                $item['quantity'];
                        }


                        /*
                        |-----------------------------------------
                        | PRICE
                        |-----------------------------------------
                        */

                        if (
                            in_array(
                                'price',
                                $itemColumns,
                                true
                            )
                        ) {

                            $itemData['price'] =
                                $item['price'];
                        }


                        /*
                        |-----------------------------------------
                        | SUBTOTAL
                        |-----------------------------------------
                        */

                        if (
                            in_array(
                                'subtotal',
                                $itemColumns,
                                true
                            )
                        ) {

                            $itemData['subtotal'] =
                                $item['subtotal'];
                        }


                        /*
                        |-----------------------------------------
                        | INSERT ITEM
                        |-----------------------------------------
                        */

                        if (!empty($itemData)) {

                            $itemFields =
                                array_keys(
                                    $itemData
                                );

                            $itemPlaceholders =
                                array_fill(
                                    0,
                                    count(
                                        $itemFields
                                    ),
                                    '?'
                                );

                            $itemSql = "
                                INSERT INTO order_items
                                (`" .
                                implode(
                                    "`, `",
                                    $itemFields
                                ) .
                                "`)
                                VALUES (" .
                                implode(
                                    ', ',
                                    $itemPlaceholders
                                ) .
                                ")
                            ";


                            $itemStmt =
                                $pdo->prepare(
                                    $itemSql
                                );


                            $itemStmt->execute(
                                array_values(
                                    $itemData
                                )
                            );
                        }
                    }
                }

            } catch (Exception $itemError) {

                /*
                | Jangan gagalkan order hanya karena
                | order_items belum tersedia.
                */

            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | KOSONGKAN CART
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['cart']
            );


            /*
            |--------------------------------------------------------------------------
            | LANJUT PAYMENT
            |--------------------------------------------------------------------------
            */

            header(
                "Location: payment.php?order_id=" .
                urlencode($order_id)
            );

            exit;


        } catch (Exception $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $error =
                $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Payment | Teddi Store</title>


<link rel="preconnect"
      href="https://fonts.googleapis.com">

<link rel="preconnect"
      href="https://fonts.gstatic.com"
      crossorigin>


<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
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

    color: #f8fafc;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(79,70,229,.18),
            transparent 30%
        ),

        radial-gradient(
            circle at 90% 15%,
            rgba(236,72,153,.13),
            transparent 30%
        ),

        linear-gradient(
            180deg,
            #0b1328,
            #101a32 55%,
            #172033
        );

    background-attachment: fixed;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    height: 72px;

    background:
        rgba(255,255,255,.96);

    border-bottom:
        1px solid rgba(255,255,255,.1);

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

    font-size: 22px;

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


/* =========================================================
   NAV
========================================================= */

.nav {

    display: flex;

    align-items: center;

    gap: 7px;
}


.nav a {

    text-decoration: none;

    color: #344054;

    font-size: 13px;

    font-weight: 600;

    padding: 10px 13px;

    border-radius: 999px;

    transition: .25s;
}


.nav a:hover {

    background: #f2f4f7;

    color: #4f46e5;
}


.nav .active {

    color: #4f46e5;

    background: #eef2ff;

    box-shadow:
        inset 0 0 0 1px #c7d2fe;
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

    padding:
        48px 0 90px;
}


/* =========================================================
   TOP HERO
========================================================= */

.checkout-hero {

    position: relative;

    overflow: hidden;

    border-radius: 27px;

    padding: 30px 35px;

    margin-bottom: 22px;

    background:

        linear-gradient(
            135deg,
            #172554,
            #1d4ed8 55%,
            #4f46e5
        );

    border:
        1px solid rgba(255,255,255,.12);

    box-shadow:
        0 25px 60px
        rgba(37,99,235,.22);
}


.checkout-hero::before {

    content: '';

    position: absolute;

    width: 280px;

    height: 280px;

    right: -110px;

    top: -150px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);
}


.checkout-hero::after {

    content: '';

    position: absolute;

    width: 140px;

    height: 140px;

    right: 160px;

    bottom: -100px;

    border-radius: 50%;

    background:
        rgba(236,72,153,.13);
}


.hero-content {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 25px;
}


.hero-text small {

    display: inline-block;

    color: #c7d2fe;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 1.5px;

    margin-bottom: 10px;
}


.hero-text h1 {

    font-size:
        clamp(28px, 4vw, 40px);

    line-height: 1.1;

    letter-spacing: -1.5px;
}


.hero-text p {

    color:
        rgba(255,255,255,.68);

    font-size: 13px;

    margin-top: 9px;
}


.secure-badge {

    padding: 11px 15px;

    border-radius: 999px;

    background:
        rgba(255,255,255,.1);

    border:
        1px solid rgba(255,255,255,.15);

    color: #d1fae5;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   ERROR
========================================================= */

.error {

    margin-bottom: 20px;

    padding: 14px 17px;

    border-radius: 14px;

    background:
        rgba(127,29,29,.35);

    border:
        1px solid rgba(248,113,113,.3);

    color: #fecaca;

    font-size: 13px;
}


/* =========================================================
   GRID
========================================================= */

.checkout-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        360px;

    gap: 22px;

    align-items: start;
}


/* =========================================================
   GLASS CARD
========================================================= */

.card {

    background:
        rgba(17,27,50,.88);

    border:
        1px solid rgba(148,163,184,.16);

    border-radius: 22px;

    box-shadow:
        0 25px 60px
        rgba(0,0,0,.18);

    backdrop-filter:
        blur(18px);

    overflow: hidden;
}


.card-header {

    padding: 24px 26px;

    border-bottom:
        1px solid rgba(148,163,184,.12);
}


.card-header h2 {

    font-size: 17px;

    font-weight: 800;
}


.card-header p {

    color:
        #94a3b8;

    font-size: 12px;

    margin-top: 5px;
}


.card-body {

    padding: 25px;
}


/* =========================================================
   SECTION TITLE
========================================================= */

.section-title {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 13px;

    font-size: 13px;

    font-weight: 800;

    color: #f8fafc;
}


.section-title .number {

    width: 25px;

    height: 25px;

    display: grid;

    place-items: center;

    border-radius: 8px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #8b5cf6
        );

    font-size: 11px;
}


/* =========================================================
   OPTIONS
========================================================= */

.options {

    display: grid;

    gap: 11px;

    margin-bottom: 28px;
}


.option {

    position: relative;
}


.option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;
}


.option label {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 15px;

    border-radius: 16px;

    cursor: pointer;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid rgba(148,163,184,.16);

    transition:
        .25s ease;
}


.option label:hover {

    border-color:
        rgba(129,140,248,.55);

    background:
        rgba(99,102,241,.08);

    transform:
        translateY(-1px);
}


.option input:checked + label {

    border-color:
        #6366f1;

    background:
        linear-gradient(
            135deg,
            rgba(99,102,241,.15),
            rgba(139,92,246,.08)
        );

    box-shadow:
        0 0 0 3px
        rgba(99,102,241,.08);
}


.radio {

    width: 20px;

    height: 20px;

    flex-shrink: 0;

    border:
        1.5px solid #64748b;

    border-radius: 50%;

    position: relative;
}


.option input:checked
+ label
.radio {

    border-color:
        #818cf8;
}


.option input:checked
+ label
.radio::after {

    content: '';

    position: absolute;

    width: 9px;

    height: 9px;

    border-radius: 50%;

    background:
        #818cf8;

    left: 50%;

    top: 50%;

    transform:
        translate(-50%, -50%);
}


.option-icon {

    width: 43px;

    height: 43px;

    flex-shrink: 0;

    display: grid;

    place-items: center;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            rgba(99,102,241,.16),
            rgba(236,72,153,.10)
        );

    font-size: 20px;
}


.option-content {

    flex: 1;
}


.option-content strong {

    display: block;

    font-size: 13px;

    color: #f8fafc;
}


.option-content span {

    display: block;

    color:
        #94a3b8;

    font-size: 11px;

    margin-top: 4px;

    line-height: 1.5;
}


.arrow {

    color:
        #64748b;

    font-size: 18px;
}


/* =========================================================
   ORDER SUMMARY
========================================================= */

.summary {

    position: sticky;

    top: 95px;
}


.summary-top {

    padding: 24px;

    background:
        linear-gradient(
            135deg,
            #172554,
            #312e81
        );

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}


.summary-top small {

    color:
        #a5b4fc;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 1px;
}


.summary-top h2 {

    font-size: 27px;

    margin-top: 7px;

    letter-spacing: -.8px;
}


.summary-body {

    padding: 23px;
}


/* =========================================================
   PRODUCT
========================================================= */

.product {

    display: flex;

    gap: 12px;

    align-items: center;

    padding-bottom: 17px;

    margin-bottom: 17px;

    border-bottom:
        1px solid
        rgba(148,163,184,.12);
}


.product-image {

    width: 54px;

    height: 54px;

    flex-shrink: 0;

    border-radius: 14px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #312e81,
            #4f46e5
        );

    display: grid;

    place-items: center;
}


.product-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}


.product-image.no-image {

    font-size: 22px;
}


.product-info {

    flex: 1;

    min-width: 0;
}


.product-name {

    color: #f8fafc;

    font-size: 12px;

    font-weight: 700;

    line-height: 1.45;
}


.product-qty {

    color: #94a3b8;

    font-size: 10px;

    margin-top: 3px;
}


.product-price {

    color: #c7d2fe;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   SUMMARY ROW
========================================================= */

.summary-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 8px 0;

    color: #94a3b8;

    font-size: 12px;
}


.summary-row strong {

    color: #e2e8f0;

    font-size: 12px;
}


.divider {

    height: 1px;

    background:
        rgba(148,163,184,.14);

    margin: 11px 0;
}


.total-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding-top: 8px;
}


.total-row span {

    color: #cbd5e1;

    font-size: 13px;
}


.total-row strong {

    color: #818cf8;

    font-size: 20px;

    font-weight: 800;
}


/* =========================================================
   SHIPPING INFO
========================================================= */

.shipping-info {

    display: flex;

    gap: 10px;

    margin-top: 18px;

    padding: 13px;

    border-radius: 13px;

    background:
        rgba(16,185,129,.07);

    border:
        1px solid
        rgba(52,211,153,.2);

    color: #a7f3d0;

    font-size: 10px;

    line-height: 1.5;
}


.shipping-info span {

    font-size: 16px;
}


/* =========================================================
   BUTTON
========================================================= */

.submit-btn {

    width: 100%;

    margin-top: 25px;

    border: none;

    padding: 16px;

    border-radius: 14px;

    cursor: pointer;

    color: white;

    font-family: inherit;

    font-size: 13px;

    font-weight: 800;

    background:
        linear-gradient(
            135deg,
            #6366f1,
            #4f46e5
        );

    box-shadow:
        0 14px 30px
        rgba(79,70,229,.3);

    transition:
        .25s ease;
}


.submit-btn:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 20px 35px
        rgba(79,70,229,.4);
}


.submit-btn:active {

    transform:
        translateY(0);
}


.secure {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 6px;

    color: #64748b;

    font-size: 10px;

    margin-top: 13px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: #94a3b8;

    text-decoration: none;

    font-size: 11px;

    font-weight: 600;

    margin-top: 20px;

    transition: .2s;
}


.back-btn:hover {

    color: #a5b4fc;

    transform:
        translateX(-3px);
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    color: #64748b;

    font-size: 10px;

    margin-top: 30px;
}


/* =========================================================
   ANIMATION
========================================================= */

.card {

    animation:
        cardIn .5s ease both;
}


.checkout-hero {

    animation:
        heroIn .55s ease both;
}


@keyframes cardIn {

    from {

        opacity: 0;

        transform:
            translateY(18px);
    }

    to {

        opacity: 1;

        transform:
            translateY(0);
    }
}


@keyframes heroIn {

    from {

        opacity: 0;

        transform:
            translateY(-12px);
    }

    to {

        opacity: 1;

        transform:
            translateY(0);
    }
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 850px) {

    .checkout-grid {

        grid-template-columns: 1fr;
    }


    .summary {

        position: static;

        order: -1;
    }

}


@media (max-width: 650px) {

    .header {

        height: auto;

        padding: 14px 0;
    }


    .header-inner {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }


    .nav {

        width: 100%;

        overflow-x: auto;
    }


    .nav a {

        white-space: nowrap;
    }


    main {

        padding-top: 28px;
    }


    .checkout-hero {

        padding: 25px 22px;
    }


    .hero-content {

        flex-direction: column;

        align-items: flex-start;
    }


    .secure-badge {

        align-self: flex-start;
    }


    .card-body {

        padding: 20px;
    }


    .card-header {

        padding: 20px;
    }


    .summary-body {

        padding: 20px;
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


        <a
            href="../index.php"
            class="logo"
        >

            Teddi <span>Store</span>

        </a>


        <nav class="nav">

            <a href="../index.php">
                Home
            </a>

            <a href="cart.php">
                Keranjang
            </a>

            <a
                href="history.php"
            >
                Riwayat
            </a>

            <a
                href="../logout.php"
                class="logout"
            >
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


    <!-- =================================================
         HERO
    ================================================== -->

    <section class="checkout-hero">

        <div class="hero-content">

            <div class="hero-text">

                <small>
                    TEDDI STORE • PAYMENT
                </small>

                <h1>
                    Konfirmasi Pembayaran
                </h1>

                <p>
                    Periksa kembali pesanan kamu
                    sebelum melanjutkan proses payment.
                </p>

            </div>


            <div class="secure-badge">

                🔒 Aman & Terpercaya

            </div>

        </div>

    </section>



    <!-- =================================================
         ERROR
    ================================================== -->

    <?php if ($error): ?>

        <div class="error">

            ⚠️
            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         CHECKOUT GRID
    ================================================== -->

    <form
        method="POST"
        id="checkoutForm"
    >

        <div class="checkout-grid">


            <!-- =========================================
                 LEFT CARD
            ========================================== -->

            <section class="card">

                <div class="card-header">

                    <h2>
                        Pilihan Payment
                    </h2>

                    <p>
                        Pilih cara pembelian dan metode
                        pembayaran yang kamu inginkan.
                    </p>

                </div>


                <div class="card-body">


                    <!-- =================================
                         PEMBELIAN
                    ================================== -->

                    <div class="section-title">

                        <div class="number">
                            1
                        </div>

                        Cara Pembelian

                    </div>


                    <div class="options">


                        <!-- OFFLINE -->

                        <div class="option">

                            <input
                                type="radio"
                                name="purchase_type"
                                value="offline"
                                id="offline"
                                checked
                            >

                            <label for="offline">

                                <div class="radio"></div>

                                <div class="option-icon">
                                    🏪
                                </div>

                                <div class="option-content">

                                    <strong>
                                        Beli Offline
                                    </strong>

                                    <span>
                                        Tanpa alamat pengiriman,
                                        langsung ambil barang di toko.
                                    </span>

                                </div>

                                <div class="arrow">
                                    ›
                                </div>

                            </label>

                        </div>



                        <!-- ONLINE -->

                        <div class="option">

                            <input
                                type="radio"
                                name="purchase_type"
                                value="online"
                                id="online"
                            >

                            <label for="online">

                                <div class="radio"></div>

                                <div class="option-icon">
                                    🚚
                                </div>

                                <div class="option-content">

                                    <strong>
                                        Beli Online
                                    </strong>

                                    <span>
                                        Gunakan alamat pengiriman
                                        untuk barang dikirim.
                                    </span>

                                </div>

                                <div class="arrow">
                                    ›
                                </div>

                            </label>

                        </div>


                    </div>



                    <!-- =================================
                         PEMBAYARAN
                    ================================== -->

                    <div class="section-title">

                        <div class="number">
                            2
                        </div>

                        Metode Pembayaran

                    </div>


                    <div class="options">


                        <!-- BANK -->

                        <div class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="bank_transfer"
                                id="bank"
                                checked
                            >

                            <label for="bank">

                                <div class="radio"></div>

                                <div class="option-icon">
                                    🏦
                                </div>

                                <div class="option-content">

                                    <strong>
                                        Transfer Bank
                                    </strong>

                                    <span>
                                        Transfer ke rekening resmi
                                        Teddi Store.
                                    </span>

                                </div>

                                <div class="arrow">
                                    ›
                                </div>

                            </label>

                        </div>



                        <!-- EWALLET -->

                        <div class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="ewallet"
                                id="ewallet"
                            >

                            <label for="ewallet">

                                <div class="radio"></div>

                                <div class="option-icon">
                                    📱
                                </div>

                                <div class="option-content">

                                    <strong>
                                        E-Wallet
                                    </strong>

                                    <span>
                                        GoPay, OVO, DANA,
                                        dan metode lainnya.
                                    </span>

                                </div>

                                <div class="arrow">
                                    ›
                                </div>

                            </label>

                        </div>



                        <!-- COD -->

                        <div class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="cod"
                                id="cod"
                            >

                            <label for="cod">

                                <div class="radio"></div>

                                <div class="option-icon">
                                    📦
                                </div>

                                <div class="option-content">

                                    <strong>
                                        Cash on Delivery
                                    </strong>

                                    <span>
                                        Bayar saat barang
                                        sampai di rumah.
                                    </span>

                                </div>

                                <div class="arrow">
                                    ›
                                </div>

                            </label>

                        </div>



                        <!-- CICILAN -->

                        <div class="option">

                            <input
                                type="radio"
                                name="payment_method"
                                value="installment"
                                id="installment"
                            >

                            <label for="installment">

                                <div class="radio"></div>

                                <div class="option-icon">
                                    📊
                                </div>

                                <div class="option-content">

                                    <strong>
                                        Cicilan
                                    </strong>

                                    <span>
                                        Cicilan hingga
                                        12 bulan.
                                    </span>

                                </div>

                                <div class="arrow">
                                    ›
                                </div>

                            </label>

                        </div>


                    </div>



                    <!-- =================================
                         BUTTON
                    ================================== -->

                    <button
                        type="submit"
                        class="submit-btn"
                    >

                        ✓ Lanjutkan ke Payment

                    </button>


                    <div class="secure">

                        🔒
                        Data transaksi kamu aman
                        dan terlindungi

                    </div>


                    <a
                        href="cart.php"
                        class="back-btn"
                    >

                        ← Kembali ke Keranjang

                    </a>


                </div>

            </section>



            <!-- =========================================
                 SUMMARY
            ========================================== -->

            <aside class="card summary">


                <div class="summary-top">

                    <small>
                        RINGKASAN BELANJA
                    </small>

                    <h2>
                        <?= rupiah($total) ?>
                    </h2>

                </div>


                <div class="summary-body">


                    <?php foreach ($items as $item): ?>


                        <div class="product">


                            <div
                                class="
                                    product-image
                                    <?=
                                    empty($item['image'])
                                    ? 'no-image'
                                    : ''
                                    ?>
                                "
                            >

                                <?php if (!empty($item['image'])): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $item['image']
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            $item['product_name']
                                        ) ?>"
                                    >

                                <?php else: ?>

                                    🛍️

                                <?php endif; ?>

                            </div>


                            <div class="product-info">

                                <div class="product-name">

                                    <?= htmlspecialchars(
                                        $item['product_name']
                                    ) ?>

                                </div>

                                <div class="product-qty">

                                    <?= $item['quantity'] ?>
                                    item

                                </div>

                            </div>


                            <div class="product-price">

                                <?= rupiah(
                                    $item['subtotal']
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>



                    <!-- SUBTOTAL -->

                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>
                            <?= rupiah($total) ?>
                        </strong>

                    </div>



                    <!-- SHIPPING -->

                    <div class="summary-row">

                        <span>
                            Pengiriman
                        </span>

                        <strong>
                            Gratis
                        </strong>

                    </div>


                    <div class="divider"></div>



                    <!-- TOTAL -->

                    <div class="total-row">

                        <span>
                            Total
                        </span>

                        <strong>
                            <?= rupiah($total) ?>
                        </strong>

                    </div>



                    <div class="shipping-info">

                        <span>
                            🎁
                        </span>

                        <div>

                            <strong>
                                Gratis biaya pengiriman
                            </strong>

                            <br>

                            Untuk pesanan dengan
                            total di atas Rp 250.000.

                        </div>

                    </div>


                </div>

            </aside>


        </div>

    </form>



    <div class="footer">

        © <?= date('Y') ?>
        Teddi Store • Payment Aman & Terpercaya

    </div>


</div>

</main>



<script>

/* =========================================================
   ANIMASI PILIHAN
========================================================= */

document
    .querySelectorAll('.option label')
    .forEach(function(label) {

        label.addEventListener(
            'click',
            function() {

                const input =
                    document.getElementById(
                        label.getAttribute('for')
                    );

                if (input) {

                    input.checked = true;
                }

            }
        );

    });


/* =========================================================
   KONFIRMASI CHECKOUT
========================================================= */

const form =
    document.getElementById(
        'checkoutForm'
    );


form.addEventListener(
    'submit',
    function(event) {

        const purchase =
            document.querySelector(
                'input[name="purchase_type"]:checked'
            );


        const payment =
            document.querySelector(
                'input[name="payment_method"]:checked'
            );


        if (!purchase || !payment) {

            event.preventDefault();

            alert(
                'Silakan pilih cara pembelian dan metode pembayaran.'
            );

            return;
        }


        const button =
            form.querySelector(
                '.submit-btn'
            );


        button.innerHTML =
            '⏳ Memproses Pesanan...';


        button.style.opacity =
            '0.75';


        button.style.pointerEvents =
            'none';

    }
);

</script>


</body>

</html>