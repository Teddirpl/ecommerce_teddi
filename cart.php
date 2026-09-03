<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=customer/cart.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['delete_cart_item'])) {
    $cart_id = isset($_POST['cart_id']) ? (int) $_POST['cart_id'] : 0;
    if ($cart_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM carts WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
    }
    header('Location: cart.php');
    exit;
}

if (isset($_POST['clear_cart'])) {
    $stmt = $pdo->prepare("DELETE FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header('Location: cart.php');
    exit;
}

if (isset($_POST['add_to_cart'])) {
    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

    if ($product_id > 0) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM carts WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $item = $stmt->fetch();

        if ($item) {
            $stmt = $pdo->prepare("UPDATE carts SET quantity = quantity + 1 WHERE id = ?");
            $stmt->execute([$item['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $product_id]);
        }

        $_SESSION['cart'] = [];
        $stmt = $pdo->prepare("SELECT c.product_id, c.quantity, p.name AS product_name, p.price, p.image FROM carts c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
        $stmt->execute([$user_id]);
        foreach ($stmt->fetchAll() as $row) {
            $_SESSION['cart'][] = [
                'product_id' => (int) $row['product_id'],
                'product_name' => $row['product_name'],
                'price' => (float) $row['price'],
                'quantity' => (int) $row['quantity'],
                'image' => $row['image'],
            ];
        }
    }
}

$stmt = $pdo->prepare("SELECT c.id as cart_id, p.id, p.name, p.price, p.image, c.quantity FROM carts c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();
$_SESSION['cart'] = [];
foreach ($cart_items as $row) {
    $_SESSION['cart'][] = [
        'product_id' => (int) $row['id'],
        'product_name' => $row['name'],
        'price' => (float) $row['price'],
        'quantity' => (int) $row['quantity'],
        'image' => $row['image'],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - E-Commerce Teddi</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/script.js" defer></script>
</head>
<body class="customer-shell cart-page-body">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container customer-page-shell cart-page-shell">
        <section class="cart-hero animate-fade-in">
            <div class="cart-hero__content">
                <span class="cart-hero__badge">Your Cart</span>
                <h1>Siap checkout, barang pilihannya sudah masuk keranjang.</h1>
                <p>Cek ulang produk, total belanja, dan lanjutkan pembayaran tanpa ribet.</p>
            </div>
        </section>

        <div class="customer-header">
            <h2>Keranjang Belanja</h2>
            <a href="shop.php" class="btn btn-secondary">← Lanjut Belanja</a>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="empty-box animate-fade-in">
                <h3>Keranjangmu masih kosong</h3>
                <p>Yuk pilih produk favoritmu dan mulai belanja sekarang.</p>
            </div>
        <?php else: ?>
            <div class="cart-layout animate-fade-in">
                <div class="cart-panel">
                    <div class="cart-list">
                        <?php
                        $grand_total = 0;
                        foreach ($cart_items as $ci):
                            $subtotal = $ci['price'] * $ci['quantity'];
                            $grand_total += $subtotal;
                        ?>
                            <div class="cart-item">
                                <?php $cart_image = product_image_src($ci['image'] ?? null); ?>
                                <?php if ($cart_image !== ''): ?>
                                    <img class="cart-item-image" src="<?= htmlspecialchars($cart_image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($ci['name']) ?>">
                                <?php else: ?>
                                    <?= render_product_icon($ci['name'], '', 'small') ?>
                                <?php endif; ?>
                                <div class="cart-item-details">
                                    <div class="cart-item-name"><?= htmlspecialchars($ci['name']) ?></div>
                                    <div class="cart-item-price"><?= format_rupiah($ci['price']) ?></div>
                                    <div class="cart-item-quantity">
                                        <span>Jumlah: <?= (int) $ci['quantity'] ?></span>
                                    </div>
                                </div>
                                <div style="margin-left:auto; text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:0.75rem;">
                                    <div style="font-weight:800; color:#0f172a;">
                                        <?= format_rupiah($subtotal) ?>
                                    </div>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="delete_cart_item" value="1">
                                        <input type="hidden" name="cart_id" value="<?= (int) $ci['cart_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="summary-panel">
                    <h3>Ringkasan Belanja</h3>
                    <div class="summary-row">
                        <span>Total</span>
                        <strong><?= format_rupiah($grand_total) ?></strong>
                    </div>
                    <div class="summary-row total">
                        <span>Grand Total</span>
                        <span><?= format_rupiah($grand_total) ?></span>
                    </div>
                    <a href="checkout.php" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:1rem;">Checkout</a>
                    <form method="POST" style="margin-top:0.8rem;">
                        <input type="hidden" name="clear_cart" value="1">
                        <button type="submit" class="btn btn-danger" style="width:100%; justify-content:center;">Kosongkan Keranjang</button>
                    </form>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>