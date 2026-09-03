<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function get_product_emoji($productName = '', $categoryName = '') {
    $text = strtolower(trim(($productName ?: $categoryName) ?? ''));
    $category = strtolower(trim($categoryName ?? ''));

    $emojiMap = [
        'iphone' => '📱',
        'phone' => '📱',
        'smartphone' => '📱',
        'handphone' => '📱',
        'fashion' => '👕',
        'pakaian' => '👕',
        'shirt' => '👕',
        'tshirt' => '👕',
        'elektronik' => '⚙️',
        'gadget' => '📦',
        'komputer' => '💻',
        'laptop' => '💻',
        'computer' => '💻',
        'pc' => '💻',
        'headphone' => '🎧',
        'earphone' => '🎧',
        'speaker' => '🔊',
        'audio' => '🎵',
        'sepatu' => '👟',
        'shoes' => '👟',
        'sport' => '🏃',
        'olahraga' => '🏃',
        'kesehatan' => '💊',
        'health' => '💊',
        'beauty' => '💄',
        'kosmetik' => '💄',
        'rumah' => '🏠',
        'home' => '🏠',
        'dekorasi' => '🪴',
        'decor' => '🪴',
        'makanan' => '🍔',
        'food' => '🍔',
        'minuman' => '🥤',
        'drink' => '🥤',
        'book' => '📚',
        'buku' => '📚',
        'travel' => '🧳',
        'aksesori' => '👜',
        'bag' => '👜',
        'tas' => '👜',
        'jewelry' => '💍',
        'perhiasan' => '💍',
        'otomotif' => '🚗',
        'kendaraan' => '🚗',
        'car' => '🚗',
        'office' => '📁',
        'kantor' => '📁',
        'camera' => '📷',
        'kamera' => '📷',
        'watch' => '⌚',
        'jam' => '⌚',
        'default' => '✨'
    ];

    foreach ($emojiMap as $keyword => $emoji) {
        if ($keyword !== 'default' && (strpos($text, $keyword) !== false || strpos($category, $keyword) !== false)) {
            return $emoji;
        }
    }

    return $emojiMap['default'];
}

function render_product_icon($productName = '', $categoryName = '', $size = 'medium') {
    $emoji = get_product_emoji($productName, $categoryName);
    return '<div class="product-image-fallback product-image-fallback--' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . '"><span>' . htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') . '</span></div>';
}

function formatRupiah($angka) {
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function format_rupiah($angka) {
    return formatRupiah($angka);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function uploadImage($file, $targetDir = '../uploads/') {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error upload file'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar (max 5MB)'];
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan (jpg, png, webp)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $targetDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    }
    
  return ['success' => false, 'message' => 'Gagal menyimpan file'];
}

function product_image_src(?string $imageName): string {
    $imageName = trim((string) $imageName);
    if ($imageName === '') {
        return '';
    }

    $targetPath = __DIR__ . '/../uploads/products/' . basename($imageName);
    if (is_file($targetPath)) {
        return '../uploads/products/' . rawurlencode(basename($imageName));
    }

    return '';
}

function get_product_image_url(?string $imageName): string {
    return product_image_src($imageName);
}

function render_product_image(?string $imageName, string $alt = '', string $className = 'product-image', bool $lazy = true): string {
    $src = product_image_src($imageName);

    if ($src !== '') {
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '"' . ($lazy ? ' loading="lazy" decoding="async"' : '') . '>';
    }

    return render_product_icon($alt ?: 'Produk', 'Umum', 'medium');
}
?>