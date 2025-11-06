<?php
// includes/auth.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php?action=login');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    if (!isset($_SESSION['user'])) {
        $row = db_query("SELECT id, username, role FROM users WHERE id = ?", [$_SESSION['user_id']]);
        $_SESSION['user'] = $row[0] ?? null;
    }
    return $_SESSION['user'];
}

function requireRole($role) {
    $user = currentUser();
    if (!$user || ($user['role'] !== $role && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo "403 Forbidden - Bạn không có quyền truy cập trang này.";
        exit;
    }
}
