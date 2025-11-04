<?php
// controllers/AuthController.php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    public static function login($username, $password) {
        $user = User::findByUsername($username);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['user']); // reload on demand
            header('Location: index.php');
            exit;
        } else {
            return "Tên đăng nhập hoặc mật khẩu không đúng.";
        }
    }

    public static function logout() {
        session_unset();
        session_destroy();
        header('Location: index.php?action=login');
        exit;
    }
}
