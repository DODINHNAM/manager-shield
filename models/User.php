<?php
// models/User.php
require_once __DIR__ . '/../includes/db.php';

class User {
    public static function findByUsername($username) {
        $rows = db_query("SELECT * FROM users WHERE username = ?", [$username]);
        return $rows[0] ?? null;
    }

    public static function find($id) {
        $rows = db_query("SELECT id, username, role, created_at FROM users WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }

    public static function allManagers() {
        return db_query("SELECT id, username FROM users WHERE role='manager' ORDER BY username");
    }

    public static function all() {
        return db_query("SELECT id, username, role, created_at FROM users ORDER BY id DESC");
    }

    public static function create($username, $password, $role='manager') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_execute("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", [$username, $hash, $role]);
        $rows = db_query("SELECT LAST_INSERT_ID() AS id");
        return $rows[0]['id'] ?? null;
    }

    public static function delete($id) {
        return db_execute("DELETE FROM users WHERE id = ?", [$id]);
    }
}
