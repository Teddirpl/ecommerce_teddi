<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name        = sanitize($_POST['name']);
    $category_id = $_POST['category_id'];
    $price       = $_POST['price'];
    $stock       = $_POST['stock'];
    $desc        = sanitize($_POST['description']);

    // Proses upload gambar
    $image_name = NULL;
    if (!empty($_FILES['image']['name'])) {
        $image_name = time() . '_' . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image_name);
    }

    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category_id, $name, $desc, $price, $stock, $image_name]);
}

$products   = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk</title>
    <link rel="stylesheet" href="../../assets/style.css">
</head>
<body>
    <a href="dashboard.php">← Kembali ke Dashboard</a>
    <h2>Manajemen Produk</h2>

    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="name" placeholder="Nama Produk" required><br><br>
        <select name="category_id">
            <option value="">Pilih Kategori</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
            <?php endforeach; ?>
        </select><br><br>
        <input type="number" name="price" placeholder="Harga" required><br><br>
        <input type="number" name="stock" placeholder="Stok" required><br><br>
        <textarea name="description" placeholder="Deskripsi"></textarea><br><br>
        <input type="file" name="image"><br><br>
        <button type="submit" name="add_product">Tambah Produk</button>
    </form>

    <table border="1" cellpadding="8" style="margin-top:20px;">
        <tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th></tr>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?php if($p['image']): ?><img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" width="50"><?php endif; ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category_name'] ?? 'Umum') ?></td>
                <td><?= format_rupiah($p['price']) ?></td>
                <td><?= $p['stock'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>