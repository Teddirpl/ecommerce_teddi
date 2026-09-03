<?php
require_once 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } elseif (is_customer()) {
        header('Location: customer/shop.php');
    } else {
        header('Location: customer/shop.php');
    }
    exit;
}

header('Location: customer/shop.php');
exit;