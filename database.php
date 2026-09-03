<?php
$host = 'localhost';
$databaseName = 'ecommerce_teddi';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        'mysql:host=' . $host . ';charset=utf8mb4',
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $databaseName . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(
        'mysql:host=' . $host . ';dbname=' . $databaseName . ';charset=utf8mb4',
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    if (!is_dir(__DIR__ . '/../uploads/products')) {
        mkdir(__DIR__ . '/../uploads/products', 0777, true);
    }

    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(150) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $categoryColumns = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('slug', $categoryColumns, true)) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN slug VARCHAR(150) NULL AFTER name");
        $existingCategories = $pdo->query("SELECT id, name FROM categories WHERE slug IS NULL OR slug = ''")->fetchAll();
        $updateCategory = $pdo->prepare("UPDATE categories SET slug = ? WHERE id = ?");

        foreach ($existingCategories as $category) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $category['name']), '-'));
            $updateCategory->execute([$slug ?: 'kategori-' . $category['id'], $category['id']]);
        }

        $pdo->exec("ALTER TABLE categories MODIFY COLUMN slug VARCHAR(150) NOT NULL");
    }

    // Products table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            category_id INT,
            price DECIMAL(10, 2) NOT NULL,
            stock INT DEFAULT 0,
            image VARCHAR(255),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )
    ");

    // Carts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS carts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");

    // Orders table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            address TEXT NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            status ENUM('pending', 'paid', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Order Items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");

    // Payments table (optional, for payment tracking)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            method VARCHAR(50) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
    ");

    // Product reviews and ratings
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Compatibility migration for older databases
    $orderColumns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('payment_method', $orderColumns)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) NULL AFTER status");
    }

    if (in_array('total_amount', $orderColumns) && !in_array('total_price', $orderColumns)) {
        $pdo->exec("ALTER TABLE orders CHANGE total_amount total_price DECIMAL(10,2) NOT NULL DEFAULT 0");
    }

    if (in_array('shipping_address', $orderColumns) && !in_array('address', $orderColumns)) {
        $pdo->exec("ALTER TABLE orders CHANGE shipping_address address TEXT NOT NULL");
    }

    if (!in_array('total_price', $orderColumns) && !in_array('total_amount', $orderColumns)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER user_id");
    }

    if (!in_array('address', $orderColumns) && !in_array('shipping_address', $orderColumns)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN address TEXT NOT NULL AFTER user_id");
    }

    $defaultUsers = [
        [
            'name' => 'admin',
            'email' => 'admin@teddi.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin'
        ],
        [
            'name' => 'customer',
            'email' => 'customer@teddi.com',
            'password' => password_hash('12345678', PASSWORD_DEFAULT),
            'role' => 'customer'
        ]
    ];

    foreach ($defaultUsers as $user) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$user['email']]);

        if (!$stmt->fetch()) {
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $insert->execute([$user['name'], $user['email'], $user['password'], $user['role']]);
        }
    }
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>