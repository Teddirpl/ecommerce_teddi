<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
    header('Location: customer/shop.php');
    exit;
}

$error = '';
$success = '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$postData = $_POST ?? [];

if ($requestMethod === 'POST') {
    if (!verify_csrf_token($postData['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $name = trim((string) ($postData['name'] ?? ''));
        $email = trim((string) ($postData['email'] ?? ''));
        $password = (string) ($postData['password'] ?? '');;
        $confirmPassword = (string) ($postData['confirm_password'] ?? '');

        if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
            $error = 'Semua field wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR name = ? LIMIT 1');
            $stmt->execute([$email, $name]);
            if ($stmt->fetch()) {
                $error = 'Nama atau email sudah terdaftar.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
                $insert->execute([$name, $email, $hash, 'customer']);

                $success = 'Pendaftaran berhasil. Silakan login.';
                $name = '';
                $email = '';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - E-Commerce Teddi</title>
    <style>
        :root {
            --bg-1: #fff7f2;
            --bg-2: #ffe9d9;
            --card: rgba(255, 255, 255, 0.9);
            --primary: #ee4d2d;
            --primary-dark: #d93d1a;
            --secondary: #f59e0b;
            --text: #1f2937;
            --muted: #475569;
            --border: rgba(148, 163, 184, 0.25);
            --danger: #ef4444;
            --success: #16a34a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: radial-gradient(circle at top left, rgba(238, 77, 45, 0.18), transparent 28%),
                        radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.14), transparent 24%),
                        linear-gradient(135deg, var(--bg-1), var(--bg-2));
            color: var(--text);
            padding: 24px;
        }
        .card {
            width: 100%; max-width: 500px; background: rgba(255, 255, 255, 0.9); border: 1px solid var(--border); border-radius: 24px; padding: 32px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.1);
        }
        h2 { margin: 0 0 8px; text-align: center; color: var(--text); }
        .subtitle { text-align: center; color: var(--muted); margin-bottom: 24px; }
        form { display: flex; flex-direction: column; gap: 18px; }
        label { display: block; margin-bottom: 8px; font-size: 0.92rem; color: var(--muted); }
        input { width: 100%; padding: 14px; border-radius: 12px; border: 1px solid var(--border); background: rgba(255, 255, 255, 0.95); color: var(--text); }
        input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(238, 77, 45, 0.12); }
        button { border: none; border-radius: 12px; padding: 14px 20px; font-weight: 700; color: white; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); cursor: pointer; }
        .alert { padding: 12px 14px; border-radius: 12px; border: 1px solid; margin-bottom: 8px; }
        .error { background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.25); color: #b91c1c; }
        .success { background: rgba(22, 163, 74, 0.1); border-color: rgba(22, 163, 74, 0.25); color: #166534; }
        .meta { text-align: center; margin-top: 18px; color: var(--muted); }
        .meta a { color: var(--primary); text-decoration: none; font-weight: 700; }
    </style>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="card">
        <h2>Daftar Akun</h2>
        <div class="subtitle">Buat akun baru untuk mulai belanja di Teddi Store</div>

        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

            <div>
                <label for="name">Nama</label>
                <input id="name" name="name" type="text" value="<?= htmlspecialchars($name ?? '') ?>" placeholder="Masukkan nama Anda" required>
            </div>

            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="contoh@email.com" required>
            </div>

            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="Minimal 8 karakter" required>
            </div>

            <div>
                <label for="confirm_password">Konfirmasi Password</label>
                <input id="confirm_password" name="confirm_password" type="password" placeholder="Ulangi password" required>
            </div>

            <button type="submit">Daftar</button>
        </form>

        <div class="meta">
            Sudah punya akun? <a href="login.php">Masuk sekarang</a>
        </div>
    </div>
</body>
</html>
