<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!isset($_SESSION['user_id'])) {
            setFlashMessage('error', 'Silakan login terlebih dahulu');
            $login_url = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/customer/') !== false)
                ? '../login.php'
                : 'login.php';
            redirect($login_url);
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin() {
        require_login();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            setFlashMessage('error', 'Akses ditolak. Halaman hanya untuk admin');
            redirect('../index.php');
        }
    }
}

if (!function_exists('require_customer')) {
    function require_customer() {
        require_login();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
            setFlashMessage('error', 'Akses ditolak');
            redirect('../index.php');
        }
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('is_customer')) {
    function is_customer() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
    }
}

if (!function_exists('get_current_user')) {
    function get_current_user() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
}

if (!function_exists('logout')) {
    function logout() {
        session_unset();
        session_destroy();
        setFlashMessage('success', 'Berhasil logout');
        redirect('login.php');
    }
}
?>