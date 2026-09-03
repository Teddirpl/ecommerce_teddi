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
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$postData = $_POST ?? [];
$redirectTo = $postData['redirect'] ?? $_GET['redirect'] ?? 'customer/shop.php';

if ($requestMethod === 'POST') {
    if (!verify_csrf_token($postData['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $name = sanitize($postData['name'] ?? '');
        $password = $postData['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ? OR email = ? LIMIT 1");
        $stmt->execute([$name, $name]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role']      = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: ' . $redirectTo);
            }
            exit;
        } else {
            $error = 'Nama atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce Teddi</title>
    <style>
        :root {
            --bg-1: #fff7f2;
            --bg-2: #ffe9d9;
            --card: rgba(255, 255, 255, 0.85);
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
            background: radial-gradient(circle at top left, rgba(238, 77, 45, 0.18), transparent 30%),
                        radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.16), transparent 28%),
                        linear-gradient(135deg, var(--bg-1), var(--bg-2));
            color: var(--text);
            padding: 24px;
            overflow: hidden;
            position: relative;
        }

        body::before,
        body::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            filter: blur(20px);
            opacity: 0.6;
            animation: floatOrb 10s ease-in-out infinite alternate;
        }

        body::before {
            width: 280px;
            height: 280px;
            background: rgba(238, 77, 45, 0.18);
            top: -80px;
            left: -40px;
        }

        body::after {
            width: 320px;
            height: 320px;
            background: rgba(245, 158, 11, 0.18);
            right: -60px;
            bottom: -80px;
            animation-delay: 1.5s;
        }

        @keyframes floatOrb {
            0% { transform: translateY(0) translateX(0) scale(1); }
            100% { transform: translateY(20px) translateX(15px) scale(1.08); }
        }

        .login-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
            background: rgba(255, 255, 255, 0.55);
            border: 1px solid var(--border);
            border-radius: 28px;
            overflow: hidden;
            backdrop-filter: blur(12px);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
            animation: slideUp 0.9s ease-out both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .promo {
            padding: 36px;
            background: linear-gradient(160deg, rgba(238, 77, 45, 0.12), rgba(255, 255, 255, 0.6));
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .promo::before {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(238, 77, 45, 0.2);
            border-radius: 50%;
            right: -40px;
            top: -40px;
            animation: pulseRing 5s ease-in-out infinite;
        }

        @keyframes pulseRing {
            0%, 100% { transform: scale(0.9); opacity: 0.5; }
            50% { transform: scale(1.15); opacity: 1; }
        }

        .promo h1 {
            font-size: 2.3rem;
            margin: 0 0 14px;
            animation: fadeInLeft 0.8s ease-out both;
            color: var(--text);
        }

        .promo p {
            color: var(--muted);
            margin: 0 0 28px;
            line-height: 1.6;
            animation: fadeInLeft 0.9s ease-out 0.1s both;
        }

        .demo-box {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px 20px;
            margin-top: 12px;
            transform: translateY(0);
            animation: fadeInLeft 1s ease-out 0.2s both;
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-18px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .demo-box h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: var(--text);
        }

        .demo-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 0.92rem;
            color: var(--muted);
        }

        .demo-row strong {
            color: var(--text);
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            animation: fadeInRight 0.9s ease-out 0.2s both;
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(18px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand h2 {
            margin: 0;
            font-size: 2rem;
            color: var(--text);
        }

        .brand span {
            color: var(--muted);
            font-size: 0.92rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.92rem;
            color: var(--muted);
        }

        input {
            width: 100%;
            padding: 14px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.9);
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.2s ease;
        }

        input:hover {
            transform: translateY(-1px);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(238, 77, 45, 0.15);
        }

        button {
            border: none;
            border-radius: 12px;
            padding: 14px 20px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 12px 24px rgba(238, 77, 45, 0.2);
        }

        button:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 18px 28px rgba(238, 77, 45, 0.28);
        }

        button:active {
            transform: translateY(0);
        }

        .error {
            background: rgba(248, 113, 113, 0.12);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .login-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="login-shell">
        <section class="promo">
            <h1>E-Commerce Teddi</h1>
            <p>Selamat datang di toko digital kami. Login cepat untuk mengakses dashboard admin atau toko customer dengan akun demo yang sudah disiapkan.</p>

            <div class="demo-box">
                <h3>Akun Demo</h3>
                <div class="demo-row"><span>Admin</span><strong>admin / admin123</strong></div>
                <div class="demo-row"><span>Customer</span><strong>customer / 12345678</strong></div>
            </div>
        </section>

        <section class="login-card">
            <div class="brand">
                <h2>Masuk</h2>
                <span>Silakan masuk dengan nama dan kata sandi</span>
            </div>

            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>">

                <div>
                    <label for="name">Nama</label>
                    <input id="name" type="text" name="name" placeholder="Masukkan nama Anda" required>
                </div>

                <div>
                    <label for="password">Kata Sandi</label>
                    <input id="password" type="password" name="password" placeholder="Masukkan kata sandi" required>
                </div>

                <button type="submit">Masuk</button>
            </form>

            <p style="margin-top: 1rem; text-align: center; color: var(--muted);">
                Belum punya akun?
                <a href="register.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Daftar di sini</a>
            </p>
        </section>
    </div>
</body>
</html>