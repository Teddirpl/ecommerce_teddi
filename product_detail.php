<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = null;
$review_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$user_id) {
        header('Location: ../login.php?redirect=' . urlencode('customer/product_detail.php?id=' . $id));
        exit;
    }

    $rating = isset($_POST['rating']) ? max(1, min(5, (int) $_POST['rating'])) : 0;
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($rating >= 1 && $rating <= 5 && $comment !== '') {
        $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $user_id, $rating, $comment]);
        header('Location: product_detail.php?id=' . urlencode((string) $id));
        exit;
    }

    $review_error = 'Mohon berikan rating dan komentar yang valid.';
}

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$product) {
    header('Location: shop.php');
    exit;
}

$rating_summary = $pdo->prepare("SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_reviews FROM product_reviews WHERE product_id = ?");
$rating_summary->execute([$id]);
$rating_data = $rating_summary->fetch(PDO::FETCH_ASSOC) ?: ['avg_rating' => 0, 'total_reviews' => 0];

$reviews = $pdo->prepare("SELECT r.*, u.name AS user_name FROM product_reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$reviews->execute([$id]);
$reviews = $reviews->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Teddi Store</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="container customer-page-shell">
    <div class="detail-layout animate-fade-in">
        <div class="detail-gallery">
            <?php $detail_image = product_image_src($product['image'] ?? null); ?>
            <?php if ($detail_image !== ''): ?>
                <img class="detail-gallery-image" src="<?= htmlspecialchars($detail_image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <?= render_product_icon($product['name'], $product['category_name'] ?? 'Umum', 'large') ?>
            <?php endif; ?>
        </div>

        <div class="detail-content">
            <span class="badge badge-primary"><?= htmlspecialchars($product['category_name'] ?? 'Umum') ?></span>
            <h1 class="detail-title"><?= htmlspecialchars($product['name']) ?></h1>
            <div class="detail-price">Rp <?= number_format((float) $product['price'], 0, ',', '.') ?></div>

            <div class="detail-meta">
                <div class="detail-meta-item">Stok: <?= (int) $product['stock'] ?></div>
                <div class="detail-meta-item">Kategori: <?= htmlspecialchars($product['category_name'] ?? 'Umum') ?></div>
            </div>

            <p class="detail-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

            <div class="detail-actions">
                <?php if ($user_id): ?>
                    <form method="POST" action="cart.php" style="display: contents;">
                        <input type="hidden" name="add_to_cart" value="1">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <button type="submit" class="btn btn-primary">Tambah ke Keranjang</button>
                    </form>
                <?php else: ?>
                    <a href="../login.php?redirect=customer%2Fproduct_detail.php%3Fid%3D<?= (int) $product['id'] ?>" class="btn btn-primary">Daftar untuk membeli</a>
                <?php endif; ?>
                <a href="shop.php" class="btn btn-secondary">Kembali</a>
            </div>
        </div>
    </div>

    <section class="review-section animate-fade-in">
        <div class="review-summary">
            <div>
                <span class="review-label">Rating Produk</span>
                <div class="review-score">
                    <span class="stars"><?= str_repeat('★', (int) round((float) ($rating_data['avg_rating'] ?? 0))) ?><?= str_repeat('☆', 5 - (int) round((float) ($rating_data['avg_rating'] ?? 0))) ?></span>
                    <strong><?= number_format((float) ($rating_data['avg_rating'] ?? 0), 1) ?></strong>
                </div>
            </div>
            <div class="review-count"><?= (int) ($rating_data['total_reviews'] ?? 0) ?> ulasan</div>
        </div>

        <div class="review-box">
            <h3>Tulis Ulasan</h3>

            <?php if ($review_error): ?>
                <div class="review-alert"><?= htmlspecialchars($review_error) ?></div>
            <?php endif; ?>

            <form method="POST" class="review-form">
                <input type="hidden" name="submit_review" value="1">

                <div class="review-field">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating" required>
                        <option value="5">5 - Sangat Bagus</option>
                        <option value="4">4 - Bagus</option>
                        <option value="3">3 - Cukup</option>
                        <option value="2">2 - Kurang</option>
                        <option value="1">1 - Buruk</option>
                    </select>
                </div>

                <div class="review-field">
                    <label for="comment">Komentar</label>
                    <textarea id="comment" name="comment" rows="4" placeholder="Tulis komentar kamu tentang produk ini..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Kirim Ulasan</button>
            </form>
        </div>

        <div class="review-list">
            <h3>Ulasan Pembeli</h3>

            <?php if (empty($reviews)): ?>
                <div class="review-empty">Belum ada ulasan untuk produk ini. Jadilah yang pertama memberi review.</div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <article class="review-item">
                        <div class="review-item__top">
                            <div>
                                <strong><?= htmlspecialchars($review['user_name']) ?></strong>
                                <div class="review-date"><?= date('d M Y', strtotime($review['created_at'])) ?></div>
                            </div>
                            <div class="review-stars"><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></div>
                        </div>
                        <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
