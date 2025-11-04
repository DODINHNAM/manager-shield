<?php
// models/User.php
require_once __DIR__ . '/../includes/db.php';

class User {
    public static function findByUsername($username) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public static function find($id) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function allManagers() {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT id, username FROM users WHERE role='manager' ORDER BY username");
        return $stmt->fetchAll();
    }

    public static function all() {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public static function create($username, $password, $role='manager') {
        $pdo = getPDO();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hash, $role]);
        return $pdo->lastInsertId();
    }

    public static function update($id, $username, $role) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        return $stmt->execute([$username, $role, $id]);
    }

    public static function delete($id) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
