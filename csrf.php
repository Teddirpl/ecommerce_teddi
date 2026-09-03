<?php
require_once __DIR__ . '/functions.php';

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . sanitize($token) . '">';
}

function csrf_meta() {
    $token = generate_csrf_token();
    return '<meta name="csrf-token" content="' . sanitize($token) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}
?>